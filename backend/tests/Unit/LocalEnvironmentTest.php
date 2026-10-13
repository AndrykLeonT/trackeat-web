<?php

namespace Tests\Unit;

use App\Support\LocalEnvironment;
use Illuminate\Encryption\Encrypter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class LocalEnvironmentTest extends TestCase
{
    #[DataProvider('emptyKeys')]
    public function test_missing_or_empty_key_generates_a_working_key(string $contents): void
    {
        $key = LocalEnvironment::applicationKey($contents);
        $this->assertIsString($key);
        $this->assertStringStartsWith('base64:', $key);
        $encrypter = new Encrypter(base64_decode(substr($key, 7), true), 'AES-256-CBC');
        $this->assertSame('TrackEat', $encrypter->decryptString($encrypter->encryptString('TrackEat')));
    }

    public static function emptyKeys(): array
    {
        return [
            'missing' => ["APP_DEBUG=true\n"],
            'empty followed by debug' => ["APP_KEY=\nAPP_DEBUG=true\n"],
            'empty CRLF' => ["APP_KEY=\r\nAPP_DEBUG=true\r\n"],
            'horizontal spaces' => ["APP_KEY = \t\nAPP_DEBUG=true\n"],
            'double quotes' => ["APP_KEY=\"\"\nAPP_DEBUG=true\n"],
            'single quotes' => ["APP_KEY=''\nAPP_DEBUG=true\n"],
            'comment' => ["APP_KEY= # Generate on first setup\nAPP_DEBUG=true\n"],
        ];
    }

    public function test_the_actual_example_environment_generates_a_key(): void
    {
        $contents = file_get_contents(__DIR__.'/../../.env.example');
        $this->assertStringStartsWith('base64:', LocalEnvironment::applicationKey($contents));
    }

    #[DataProvider('existingKeys')]
    public function test_existing_valid_keys_are_preserved(string $contents): void
    {
        $this->assertNull(LocalEnvironment::applicationKey($contents));
    }

    public static function existingKeys(): array
    {
        $key = 'base64:'.base64_encode(str_repeat('x', 32));

        return [
            'unquoted' => ["APP_KEY=$key\nAPP_DEBUG=true\n"],
            'double quoted' => ["APP_KEY=\"$key\"\n"],
            'single quoted' => ["APP_KEY='$key'\n"],
            'raw key' => ['APP_KEY='.str_repeat('x', 32)."\n"],
        ];
    }

    #[DataProvider('invalidKeys')]
    public function test_invalid_existing_keys_are_rejected_instead_of_rotated(string $key): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('APP_KEY');
        LocalEnvironment::applicationKey("APP_KEY=\"$key\"\nAPP_DEBUG=true\n");
    }

    public static function invalidKeys(): array
    {
        return [['base64:!not-base64!'], ['base64:'.base64_encode('too-short')], ['not-a-valid-key']];
    }
}
