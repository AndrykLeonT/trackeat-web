<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Práctica 6 · Persona 2: protecciones del registro (campo trampa; después captcha y límite de intentos).
class RegistroSeguroTest extends TestCase
{
    use RefreshDatabase;

    private function datos(array $extra = []): array
    {
        return [
            'name' => 'Persona Real',
            'email' => 'persona@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
            ...$extra,
        ];
    }

    public function test_el_campo_trampa_lleno_rechaza_el_registro(): void
    {
        $this->from('/register')->post('/register', $this->datos(['website' => 'http://spam.example']))
            ->assertRedirect('/register')
            ->assertSessionHasErrors(['email' => 'No se pudo completar el registro. Intenta de nuevo.']);

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'persona@example.test']);
    }

    public function test_con_el_campo_trampa_vacio_el_registro_funciona(): void
    {
        $this->post('/register', $this->datos(['website' => '']))
            ->assertRedirect(route('home', absolute: false));

        $this->assertAuthenticated();
        $this->assertSame('cliente', User::where('email', 'persona@example.test')->value('rol'));
    }
}
