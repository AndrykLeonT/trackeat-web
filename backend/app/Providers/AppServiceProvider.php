<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(fn ($user, string $token) => rtrim(config('app.url'), '/')
            .route('password.reset', ['token' => $token], absolute: false));

        Password::defaults(fn () => Password::min(8)->rules([
            function (string $attribute, mixed $value, \Closure $fail) {
                if (is_string($value) && config('hashing.driver') === 'bcrypt' && (strlen($value) > 72 || str_contains($value, "\0"))) {
                    $fail('La contraseña admite hasta 72 bytes y no puede contener caracteres nulos.');
                }
            },
        ]));

        // Límite de intentos del registro (Práctica 6, medida 4): 5 por minuto por IP. Va por IP y no por
        // correo porque quien crea cuentas en masa cambia de correo cada vez. El contador vive en la caché
        // compartida, así que cambiar de nodo detrás del balanceador no lo reinicia.
        RateLimiter::for('registro', fn (Request $request) => Limit::perMinute(5)
            ->by('registro|'.$request->ip())
            ->response(fn (Request $request, array $headers) => back()
                ->withInput($request->only('name', 'email'))
                ->withErrors(['email' => 'Demasiados intentos de registro. Espera un minuto e inténtalo de nuevo.'])
                ->withHeaders($headers)));
    }
}
