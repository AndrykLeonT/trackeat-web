<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class UserAccounts
{
    public const ROLES = ['cliente', 'cocinero', 'mesero', 'cajero', 'administrador'];

    public const STATIONS = ['general', 'cocina', 'barra'];

    public const PROFILE_FIELDS = ['email', 'primer_nombre', 'segundo_nombre', 'primer_apellido', 'segundo_apellido', 'telefono'];

    public function saveProfile(User $actor, array $input): User
    {
        return $this->transaction(function () use ($actor, $input) {
            $user = User::query()->where('eliminado', false)->whereNull('demo_room')->lockForUpdate()->findOrFail($actor->id);
            $details = $this->details($input, $user);
            if ($details['email'] !== $user->email) {
                $this->requirePassword($user, $input);
            }

            return $this->saveDetails($user, $details);
        });
    }

    public function changePassword(User $actor, array $input): User
    {
        return DB::transaction(function () use ($actor, $input) {
            $user = User::query()->where('eliminado', false)->whereNull('demo_room')->lockForUpdate()->findOrFail($actor->id);
            $this->requirePassword($user, $input);
            $data = Validator::make($input, ['password' => ['required', 'confirmed', PasswordRule::defaults()]])->validate();
            Password::deleteToken($user);
            $user->forceFill(['password' => $data['password'], 'remember_token' => Str::random(60)])->save();

            return $user;
        }, 3);
    }

    public function saveAsAdministrator(User $actor, array $input, ?int $id = null): void
    {
        $this->transaction(function () use ($actor, $input, $id) {
            $administrators = $this->lockAdministrators($actor);
            $user = $id === null ? new User : User::query()->whereNull('demo_room')->lockForUpdate()->findOrFail($id);
            $details = $this->details($input, $user);
            $access = Validator::make($input, [
                'rol' => ['required', Rule::in(self::ROLES)],
                'estacion' => ['nullable', 'exclude_unless:rol,cocinero', Rule::in(self::STATIONS)],
                'password' => $user->exists ? ['prohibited'] : ['required', 'confirmed', PasswordRule::defaults()],
            ])->validate();

            if ($access['rol'] !== 'administrador') {
                $this->preserveAdministrator($administrators, $user, 'rol');
            }

            $user->forceFill([
                'rol' => $access['rol'],
                'estacion' => $access['rol'] === 'cocinero' ? ($access['estacion'] ?? 'general') : null,
            ]);
            if (! $user->exists) {
                $user->password = $access['password'];
            }

            $this->saveDetails($user, $details);
        });
    }

    public function deactivate(User $actor, int $id): void
    {
        DB::transaction(function () use ($actor, $id) {
            $administrators = $this->lockAdministrators($actor);
            $user = User::query()->whereNull('demo_room')->lockForUpdate()->findOrFail($id);
            $this->preserveAdministrator($administrators, $user, 'cuenta');

            if (! $user->eliminado) {
                Password::deleteToken($user);
                $user->forceFill(['eliminado' => true, 'remember_token' => Str::random(60)])->save();
            }
        }, 3);
    }

    private function details(array $input, User $user): array
    {
        if (is_string($input['email'] ?? null)) {
            $input['email'] = Str::lower(trim($input['email']));
        }

        return Validator::make($input, [
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'primer_nombre' => ['required', 'string', 'max:80'],
            'segundo_nombre' => ['nullable', 'string', 'max:80'],
            'primer_apellido' => ['nullable', 'string', 'max:80'],
            'segundo_apellido' => ['nullable', 'string', 'max:80'],
            'telefono' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 ().-]{7,29}$/'],
        ])->validate();
    }

    private function saveDetails(User $user, array $details): User
    {
        $user->fill($details);
        $name = trim(implode(' ', array_filter(array_map(fn ($field) => $user->{$field}, [
            'primer_nombre', 'segundo_nombre', 'primer_apellido', 'segundo_apellido',
        ]))));
        if (mb_strlen($name) > 255) {
            throw ValidationException::withMessages(['primer_nombre' => 'El nombre completo admite hasta 255 caracteres.']);
        }

        if ($user->exists && $user->isDirty('email')) {
            $previous = (new User)->forceFill(['email' => $user->getOriginal('email')]);
            Password::deleteToken($previous);
            $user->forceFill(['email_verified_at' => null, 'remember_token' => Str::random(60)]);
        }

        $user->name = $name;
        $user->save();

        return $user;
    }

    private function lockAdministrators(User $actor): Collection
    {
        // Todas las altas y modificaciones administrativas toman estos bloqueos en el mismo orden.
        $administrators = User::query()->where('rol', 'administrador')->where('eliminado', false)
            ->whereNull('demo_room')->orderBy('id')->lockForUpdate()->get(['id']);
        abort_unless($administrators->contains($actor->id), 403);

        return $administrators;
    }

    private function preserveAdministrator(Collection $administrators, User $user, string $field): void
    {
        if ($administrators->count() === 1 && $administrators->contains($user->id)) {
            throw ValidationException::withMessages([$field => 'Debe quedar al menos un administrador activo.']);
        }
    }

    private function requirePassword(User $user, array $input): void
    {
        $data = Validator::make($input, ['current_password' => ['required', 'string']])->validate();
        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'La contraseña actual es incorrecta.']);
        }
    }

    private function transaction(callable $callback): mixed
    {
        try {
            return DB::transaction($callback, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'Este correo ya está en uso.']);
        }
    }
}
