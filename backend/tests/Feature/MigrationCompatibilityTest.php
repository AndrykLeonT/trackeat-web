<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MigrationCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_extending_users_preserves_existing_accounts_and_passwords(): void
    {
        $migration = require database_path('migrations/2026_10_13_000001_extend_users_for_trackeat.php');
        $migration->down();
        $hash = Hash::make('existing-password');
        $id = DB::table('users')->insertGetId(['name' => 'Cuenta Anterior', 'email' => 'OLD@example.test',
            'password' => $hash, 'created_at' => now(), 'updated_at' => now()]);
        $migration->up();
        $user = User::findOrFail($id);
        $this->assertSame('Cuenta Anterior', $user->name);
        $this->assertSame('Cuenta Anterior', $user->primer_nombre);
        $this->assertNull($user->primer_apellido);
        $this->assertSame('old@example.test', $user->email);
        $this->assertSame($hash, $user->password);
        $this->assertSame('cliente', $user->rol);
        $this->assertFalse($user->eliminado);
    }
}
