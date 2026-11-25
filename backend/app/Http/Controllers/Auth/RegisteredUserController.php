<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\Turnstile;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    // Mismo mensaje para cualquier registro rechazado que no sea un error de captura del usuario.
    public const NO_SE_PUDO = 'No se pudo completar el registro. Intenta de nuevo.';

    /**
     * Show the registration page.
     */
    public function create(): Response
    {
        return Inertia::render('auth/register', ['turnstileSiteKey' => Turnstile::llaveDelSitio()]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        // Campo trampa (Práctica 6, medida 3): una persona no lo ve; si llega con texto, lo llenó un bot.
        // El mensaje es genérico para no avisarle al bot que cayó en la trampa.
        if ($request->filled('website')) {
            throw ValidationException::withMessages(['email' => self::NO_SE_PUDO]);
        }

        // Captcha primero: sin él no se revisa nada más (por ejemplo, si el correo ya existe).
        $request->validate([Turnstile::CAMPO => Turnstile::reglas()], [Turnstile::CAMPO.'.required' => Turnstile::MENSAJE]);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'primer_nombre' => 'nullable|string|max:255',
            'segundo_nombre' => 'nullable|string|max:255',
            'primer_apellido' => 'nullable|string|max:255',
            'segundo_apellido' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:30',
        ]);

        // Error genérico (Práctica 6, medida 2): no se dice que el correo ya está registrado,
        // se responde igual que el campo trampa para no revelar qué cuentas existen.
        if (User::where('email', $request->email)->exists()) {
            throw ValidationException::withMessages(['email' => self::NO_SE_PUDO]);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'primer_nombre' => $request->input('primer_nombre') ?: $request->name,
            'segundo_nombre' => $request->input('segundo_nombre'),
            'primer_apellido' => $request->input('primer_apellido'),
            'segundo_apellido' => $request->input('segundo_apellido'),
            'telefono' => $request->input('telefono'),
        ]);

        event(new Registered($user));

        Auth::login($user);

        return to_route('home');
    }
}
