<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityIntegrationReviewTest extends TestCase
{
    use DatabaseMigrations;

    public function test_password_hash_and_generic_login_errors(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Ficticia!2026')]);
        $this->assertTrue(Hash::check('Ficticia!2026', $user->password));
        $this->assertContains(password_get_info($user->password)['algoName'], ['bcrypt', 'argon2id', 'argon2i']);
        $known = $this->postJson('/login', ['email' => $user->email, 'password' => 'wrong'])->assertUnprocessable()->json('errors.email');
        $unknown = $this->postJson('/login', ['email' => 'missing-'.Str::uuid().'@example.test', 'password' => 'wrong'])->assertUnprocessable()->json('errors.email');
        $this->assertSame($known, $unknown);
    }

    public function test_sixth_failed_login_is_limited(): void
    {
        $email = 'missing-'.Str::uuid().'@example.test';
        for ($i = 0; $i < 5; $i++) {
            $first = $this->postJson('/login', ['email' => $email, 'password' => 'wrong'])->assertUnprocessable()->json('errors.email');
        }
        $limited = $this->postJson('/login', ['email' => $email, 'password' => 'wrong'])->assertUnprocessable()->json('errors.email');
        $this->assertNotSame($first, $limited);
    }
}
