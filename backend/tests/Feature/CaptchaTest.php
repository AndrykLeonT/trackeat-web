<?php

namespace Tests\Feature;

use App\Models\User;
use App\Rules\Turnstile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

// Práctica 6 · Persona 2: captcha de Cloudflare Turnstile en el registro y el login.
// Cloudflare se simula con Http::fake(): las pruebas no dependen de internet.
class CaptchaTest extends TestCase
{
    use RefreshDatabase;

    private const VERIFICAR = 'challenges.cloudflare.com/turnstile/v0/siteverify';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.turnstile' => ['enabled' => true, 'site_key' => 'llave-publica', 'secret_key' => 'llave-secreta']]);
    }

    private function cloudflareResponde(bool $valido): void
    {
        Http::fake([self::VERIFICAR => Http::response(['success' => $valido])]);
    }

    private function registro(array $extra = []): array
    {
        return ['name' => 'Persona Real', 'email' => 'persona@example.test', 'password' => 'password',
            'password_confirmation' => 'password', ...$extra];
    }

    private function usuario(): User
    {
        return User::factory()->create(['email' => 'cliente@example.test', 'password' => Hash::make('Correcta!2026')]);
    }

    public function test_las_paginas_reciben_la_llave_publica_y_nunca_la_secreta(): void
    {
        foreach (['/login', '/register'] as $pagina) {
            $html = $this->get($pagina)->assertOk()->getContent();
            $this->assertStringContainsString('llave-publica', $html);
            $this->assertStringNotContainsString('llave-secreta', $html);
        }
    }

    public function test_registro_sin_token_se_rechaza_sin_preguntar_a_cloudflare(): void
    {
        Http::fake();
        $this->post('/register', $this->registro())->assertSessionHasErrors([Turnstile::CAMPO => Turnstile::MENSAJE]);
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'persona@example.test']);
        Http::assertNothingSent();
    }

    public function test_registro_con_token_rechazado_no_crea_la_cuenta(): void
    {
        $this->cloudflareResponde(false);
        $this->post('/register', $this->registro([Turnstile::CAMPO => 'token-falso']))
            ->assertSessionHasErrors([Turnstile::CAMPO => Turnstile::MENSAJE]);
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'persona@example.test']);
    }

    public function test_registro_con_token_valido_crea_la_cuenta_y_manda_la_llave_secreta(): void
    {
        $this->cloudflareResponde(true);
        $this->post('/register', $this->registro([Turnstile::CAMPO => 'token-bueno']))->assertRedirect(route('home', absolute: false));
        $this->assertAuthenticated();
        Http::assertSent(fn (Request $r) => str_contains($r->url(), self::VERIFICAR)
            && $r['secret'] === 'llave-secreta' && $r['response'] === 'token-bueno');
    }

    public function test_el_captcha_se_revisa_antes_que_el_correo_repetido(): void
    {
        $this->usuario();
        $this->cloudflareResponde(false);
        // Sin captcha válido no se dice si el correo ya existe.
        $this->post('/register', $this->registro(['email' => 'cliente@example.test', Turnstile::CAMPO => 'token-falso']))
            ->assertSessionHasErrors(Turnstile::CAMPO)
            ->assertSessionDoesntHaveErrors('email');
    }

    public function test_login_sin_token_o_con_token_rechazado_no_inicia_sesion(): void
    {
        $this->usuario();
        $this->post('/login', ['email' => 'cliente@example.test', 'password' => 'Correcta!2026'])
            ->assertSessionHasErrors([Turnstile::CAMPO => Turnstile::MENSAJE]);
        $this->assertGuest();

        $this->cloudflareResponde(false);
        $this->post('/login', ['email' => 'cliente@example.test', 'password' => 'Correcta!2026', Turnstile::CAMPO => 'token-falso'])
            ->assertSessionHasErrors([Turnstile::CAMPO => Turnstile::MENSAJE]);
        $this->assertGuest();
    }

    public function test_login_con_token_valido_inicia_sesion(): void
    {
        $this->usuario();
        $this->cloudflareResponde(true);
        $this->post('/login', ['email' => 'cliente@example.test', 'password' => 'Correcta!2026', Turnstile::CAMPO => 'token-bueno'])
            ->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();
    }

    public function test_si_cloudflare_no_responde_no_se_deja_pasar(): void
    {
        $this->usuario();
        Http::fake([self::VERIFICAR => fn () => throw new ConnectionException('sin red')]);
        $this->post('/login', ['email' => 'cliente@example.test', 'password' => 'Correcta!2026', Turnstile::CAMPO => 'token-bueno'])
            ->assertSessionHasErrors([Turnstile::CAMPO => 'No se pudo verificar el captcha. Intenta de nuevo.']);
        $this->assertGuest();
    }
}
