<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class NewPasswordController extends Controller
{
    /**
     * Show the password reset page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/reset-password', [
            'email' => $request->query('email', ''),
            'token' => $request->route('token'),
        ])->toResponse($request)->header('Referrer-Policy', 'no-referrer')->header('Cache-Control', 'no-store');
    }

    /**
     * Handle an incoming new password request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => 'required|string|max:128',
            'email' => 'required|string|email|max:255',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);
        $email = Str::lower($data['email']);
        $resetUser = DB::transaction(function () use ($email, $data) {
            $user = User::query()->where('email', $email)->where('eliminado', false)
                ->whereNull('demo_room')->lockForUpdate()->first();
            if (! $user) {
                return null;
            }

            $status = Password::reset([
                'email' => $email,
                'token' => $data['token'],
                'password' => $data['password'],
            ], function (User $account, string $password) {
                $account->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            });

            return $status === Password::PasswordReset ? $user->refresh() : null;
        }, 3);

        if ($resetUser) {
            event(new PasswordReset($resetUser));

            return to_route('login')->with('status', 'Contraseña restablecida.');
        }

        throw ValidationException::withMessages([
            'email' => 'El enlace es inválido o ha caducado. Solicita otro.',
        ]);
    }
}
