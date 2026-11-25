<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserAccounts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'buscar' => ['nullable', 'string', 'max:120'],
            'rol' => ['nullable', Rule::in(UserAccounts::ROLES)],
            'estado' => ['nullable', Rule::in(['activas', 'inactivas'])],
        ]);

        $users = User::query()->whereNull('demo_room')
            ->when($filters['rol'] ?? null, fn ($query, $role) => $query->where('rol', $role))
            ->when($filters['estado'] ?? null, fn ($query, $status) => $query->where('eliminado', $status === 'inactivas'))
            ->when($filters['buscar'] ?? null, function ($query, $search) {
                $pattern = '%'.addcslashes($search, '\\%_').'%';
                $query->where(fn ($fields) => $fields->where('name', 'ilike', $pattern)->orWhere('email', 'ilike', $pattern));
            })
            ->orderBy('name')->orderBy('id')
            ->paginate(20, ['id', 'name', 'email', 'rol', 'estacion', 'eliminado'])->withQueryString();

        return Inertia::render('admin/usuarios', [
            'users' => $users,
            'roles' => UserAccounts::ROLES,
            'filters' => (object) $filters,
        ]);
    }

    public function create(): Response
    {
        return $this->form();
    }

    public function edit(int $user): Response
    {
        return $this->form(User::query()->whereNull('demo_room')->findOrFail($user));
    }

    public function store(Request $request, UserAccounts $accounts): RedirectResponse
    {
        $accounts->saveAsAdministrator($request->user(), $request->all());

        return to_route('admin.usuarios')->with('success', 'Cuenta creada.');
    }

    public function update(Request $request, UserAccounts $accounts, int $user): RedirectResponse
    {
        $actor = $request->user();
        $accounts->saveAsAdministrator($actor, $request->all(), $user);

        if ($actor->id === $user && $actor->refresh()->rol !== 'administrador') {
            return to_route('perfil')->with('success', 'Tu rol se actualizó.');
        }

        return to_route('admin.usuarios')->with('success', 'Cuenta actualizada.');
    }

    public function destroy(Request $request, UserAccounts $accounts, int $user): RedirectResponse
    {
        $actor = $request->user();
        $accounts->deactivate($actor, $user);

        if ($actor->id === $user) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return to_route('login')->with('status', 'Cuenta desactivada.');
        }

        return to_route('admin.usuarios')->with('success', 'Cuenta desactivada.');
    }

    private function form(?User $user = null): Response
    {
        return Inertia::render('admin/usuario-form', [
            'account' => $user?->only([...UserAccounts::PROFILE_FIELDS, 'id', 'name', 'rol', 'estacion', 'eliminado']),
            'roles' => UserAccounts::ROLES,
            'stations' => UserAccounts::STATIONS,
        ]);
    }
}
