<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class Money
{
    public static function parse(mixed $value, string $field = 'monto'): string
    {
        if ((! is_string($value) && ! is_int($value)) || ! preg_match('/^\d{1,10}(\.\d{1,2})?$/D', (string) $value)) {
            throw ValidationException::withMessages([$field => 'Use un decimal no negativo de hasta dos decimales, enviado como texto.']);
        }

        return bcadd((string) $value, '0', 2);
    }

    public static function add(string $a, string $b): string
    {
        return bcadd($a, $b, 2);
    }

    public static function subtract(string $a, string $b): string
    {
        return bcsub($a, $b, 2);
    }

    public static function multiply(string $price, int $quantity): string
    {
        return bcmul($price, (string) $quantity, 2);
    }
}
