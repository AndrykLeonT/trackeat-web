<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class PasswordResetLinkController extends Controller
{
    /**
     * Show the password reset link request page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => 'required|string|email|max:255']);

        try {
            if (in_array(config('mail.default'), ['smtp', 'array'], true)) {
                Password::sendResetLink([
                    'email' => Str::lower($data['email']),
                    'eliminado' => false,
                    'demo_room' => null,
                ]);
            }
        } catch (TransportExceptionInterface) {
            Log::channel('security')->warning('password_reset_delivery_failed');
        }

        return back()->with('status', 'Si la cuenta está disponible, recibirás un enlace para restablecer la contraseña.');
    }
}
