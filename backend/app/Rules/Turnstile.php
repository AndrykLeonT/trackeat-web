<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

// Captcha de Cloudflare Turnstile (Práctica 6, medida 3). El navegador entrega un token de un solo uso;
// aquí se le pregunta a Cloudflare si es válido. Si Cloudflare no contesta, se rechaza: nunca se deja pasar sin verificar.
class Turnstile implements ValidationRule
{
    public const MENSAJE = 'Confirma que no eres un robot.';

    public const CAMPO = 'cf-turnstile-response';

    private const VERIFICAR = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    // Reglas del campo del token: vacío si el captcha está apagado (solo en las pruebas automáticas).
    public static function reglas(): array
    {
        return config('services.turnstile.enabled') ? ['required', 'string', new self] : [];
    }

    // Llave pública que necesita la página para dibujar el widget, o null si el captcha está apagado.
    public static function llaveDelSitio(): ?string
    {
        return config('services.turnstile.enabled') ? config('services.turnstile.site_key') : null;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $respuesta = Http::asForm()->timeout(5)->post(self::VERIFICAR, [
                'secret' => config('services.turnstile.secret_key'),
                'response' => $value,
                'remoteip' => request()->ip(),
            ]);
        } catch (ConnectionException) {
            $fail('No se pudo verificar el captcha. Intenta de nuevo.');

            return;
        }

        if ($respuesta->json('success') !== true) {
            $fail(self::MENSAJE);
        }
    }
}
