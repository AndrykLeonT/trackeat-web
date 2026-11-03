<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SaludTest extends TestCase
{
    public function test_salud_revisa_la_base_e_identifica_el_nodo(): void
    {
        config(['trackeat.nodo' => '2']);

        $this->getJson('/salud')
            ->assertOk()
            ->assertHeader('X-Servidor', '2')
            ->assertJson(['nodo' => '2', 'bd' => 'ok']);
    }

    public function test_salud_responde_503_si_la_base_no_contesta(): void
    {
        DB::shouldReceive('select')->once()->andThrow(new \RuntimeException('sin conexion'));

        $this->getJson('/salud')
            ->assertStatus(503)
            ->assertJson(['bd' => 'caida'])
            ->assertJsonMissing(['message' => 'sin conexion']);
    }

    public function test_salud_no_abre_sesion(): void
    {
        $this->get('/salud')->assertCookieMissing(config('session.cookie'));
    }
}
