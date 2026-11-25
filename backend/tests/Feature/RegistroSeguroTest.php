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

    public function test_el_correo_repetido_no_se_revela(): void
    {
        User::factory()->create(['email' => 'persona@example.test']);

        $this->from('/register')->post('/register', $this->datos())
            ->assertRedirect('/register')
            ->assertSessionHasErrors(['email' => 'No se pudo completar el registro. Intenta de nuevo.']);

        $this->assertGuest();
        $this->assertSame(1, User::where('email', 'persona@example.test')->count());
    }

    public function test_el_sexto_intento_de_registro_en_un_minuto_se_bloquea(): void
    {
        $mensaje = 'Demasiados intentos de registro. Espera un minuto e inténtalo de nuevo.';
        // Cinco intentos fallidos (contraseña que no coincide), cada uno con un correo distinto.
        for ($i = 1; $i <= 5; $i++) {
            $this->from('/register')->post('/register', $this->datos(['email' => "intento$i@example.test", 'password_confirmation' => 'otra']))
                ->assertSessionHasErrors('password')
                ->assertSessionDoesntHaveErrors('email');
        }

        // El sexto se bloquea aunque los datos sean válidos.
        $this->from('/register')->post('/register', $this->datos(['email' => 'valido@example.test']))
            ->assertRedirect('/register')
            ->assertSessionHasErrors(['email' => $mensaje]);
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'valido@example.test']);

        // Otra IP no comparte el contador.
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])->post('/register', $this->datos(['email' => 'otra-ip@example.test']))
            ->assertRedirect(route('home', absolute: false));
        $this->assertDatabaseHas('users', ['email' => 'otra-ip@example.test']);
    }

    public function test_el_limite_se_libera_al_pasar_el_minuto(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $this->post('/register', $this->datos(['email' => "x$i@example.test", 'password_confirmation' => 'otra']));
        }
        $this->post('/register', $this->datos(['email' => 'despues@example.test']))->assertSessionHasErrors('email');

        $this->travel(61)->seconds();
        $this->post('/register', $this->datos(['email' => 'despues@example.test']))->assertRedirect(route('home', absolute: false));
        $this->assertAuthenticated();
    }

    public function test_con_el_campo_trampa_vacio_el_registro_funciona(): void
    {
        $this->post('/register', $this->datos(['website' => '']))
            ->assertRedirect(route('home', absolute: false));

        $this->assertAuthenticated();
        $this->assertSame('cliente', User::where('email', 'persona@example.test')->value('rol'));
    }
}
