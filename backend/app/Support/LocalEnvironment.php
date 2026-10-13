<?php

namespace App\Support;

use Dotenv\Dotenv;
use Illuminate\Encryption\Encrypter;
use RuntimeException;

final class LocalEnvironment
{
    /** Return a new key only when the environment has no key. */
    public static function applicationKey(string $contents): ?string
    {
        $key = Dotenv::parse($contents)['APP_KEY'] ?? '';
        if ($key === '') {
            return 'base64:'.base64_encode(Encrypter::generateKey('AES-256-CBC'));
        }

        $decoded = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;
        if ($decoded === false || ! Encrypter::supported($decoded, 'AES-256-CBC')) {
            // Never silently rotate an existing key: it may protect stored data.
            throw new RuntimeException('APP_KEY no es valida para AES-256-CBC. Corrige su valor en .env; la configuracion no se modifico.');
        }

        return null;
    }
}
