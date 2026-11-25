<?php

namespace App\Http\Controllers;

use App\Services\UserAccounts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('perfil', [
            'profile' => $user->only(UserAccounts::PROFILE_FIELDS),
            'name' => $user->name,
            'demo' => $user->demo_room !== null,
        ]);
    }

    public function update(Request $request, UserAccounts $accounts): RedirectResponse
    {
        $accounts->saveProfile($request->user(), $request->all());

        return back()->with('success', 'Perfil actualizado.');
    }

    public function changePassword(Request $request, UserAccounts $accounts): RedirectResponse
    {
        $user = $accounts->changePassword($request->user(), $request->all());
        Auth::guard('web')->setUser($user);
        $request->session()->regenerate(true);

        return back()->with('success', 'Contraseña actualizada.');
    }
}
