<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

// Navegación por rol: las vistas del personal no las abren clientes ni visitantes (no basta con ocultar el enlace).
class NavegacionPorRolTest extends TestCase
{
    use RefreshDatabase;

    private const PERSONAL = ['administrador', 'cocinero', 'mesero', 'cajero'];

    // Vistas del personal. Las dos primeras las comparten con los invitados de la demostración de clase.
    private const TABLERO = ['/dashboard', '/comandas', '/comandas/desglose'];

    private const SOLO_PERSONAL = ['/recetas', '/metricas'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    private function usuario(string $clave): User
    {
        return User::where('email', "$clave@example.test")->firstOrFail();
    }

    private function invitadoDeDemo(string $vigencia = '+1 hour'): User
    {
        config(['trackeat.demo_enabled' => true]);
        $id = (string) Str::uuid();

        $invitado = (new User)->forceFill(['name' => 'Invitado '.substr($id, 0, 6), 'primer_nombre' => 'Invitado',
            'email' => "demo-$id@example.test", 'password' => Str::random(48), 'rol' => 'cliente',
            'demo_room' => (string) Str::uuid(), 'demo_expires_at' => date('Y-m-d H:i:sP', strtotime($vigencia))]);
        $invitado->save();

        return $invitado;
    }

    public function test_un_visitante_va_a_login_al_abrir_las_vistas_del_personal(): void
    {
        foreach ([...self::TABLERO, ...self::SOLO_PERSONAL] as $ruta) {
            $this->get($ruta)->assertRedirect('/login');
        }
    }

    public function test_un_cliente_recibe_403_en_las_vistas_del_personal(): void
    {
        foreach ([...self::TABLERO, ...self::SOLO_PERSONAL] as $ruta) {
            $this->actingAs($this->usuario('cliente1'))->get($ruta)->assertForbidden();
        }
    }

    public function test_los_cuatro_roles_del_personal_abren_recetas_y_metricas(): void
    {
        foreach (self::PERSONAL as $rol) {
            foreach (self::SOLO_PERSONAL as $ruta) {
                $this->actingAs($this->usuario($rol))->get($ruta)->assertOk();
            }
        }
    }

    public function test_los_cuatro_roles_del_personal_abren_dashboard_y_comandas(): void
    {
        foreach (self::PERSONAL as $rol) {
            $this->actingAs($this->usuario($rol))->get('/dashboard')->assertOk();
            $this->actingAs($this->usuario($rol))->get('/comandas/desglose')->assertOk();
        }
        // Mesero y cajero trabajan a nivel de pedido: /comandas los lleva a su pantalla (HU-09).
        foreach (['mesero', 'cajero'] as $rol) {
            $this->actingAs($this->usuario($rol))->get('/comandas')->assertRedirect('/mesero/pedidos');
        }
        foreach (['cocinero', 'administrador'] as $rol) {
            $this->actingAs($this->usuario($rol))->get('/comandas')->assertOk();
        }
    }

    public function test_un_miembro_del_personal_dado_de_baja_ya_no_entra(): void
    {
        $mesero = $this->usuario('mesero');
        $mesero->forceFill(['eliminado' => true])->save();

        foreach ([...self::TABLERO, ...self::SOLO_PERSONAL] as $ruta) {
            $this->actingAs($mesero)->get($ruta)->assertForbidden();
        }
    }

    public function test_los_invitados_de_la_demostracion_ven_el_tablero_de_su_sala_pero_no_recetas_ni_metricas(): void
    {
        $invitado = $this->invitadoDeDemo();

        foreach (self::TABLERO as $ruta) {
            $this->actingAs($invitado)->get($ruta)->assertOk();
        }
        foreach (self::SOLO_PERSONAL as $ruta) {
            $this->actingAs($invitado)->get($ruta)->assertForbidden();
        }
    }

    public function test_un_invitado_de_demostracion_vencido_o_con_la_demo_apagada_no_entra(): void
    {
        $vencido = $this->invitadoDeDemo('-1 hour');
        $this->actingAs($vencido)->get('/dashboard')->assertForbidden();

        $vigente = $this->invitadoDeDemo();
        config(['trackeat.demo_enabled' => false]);
        $this->actingAs($vigente)->get('/dashboard')->assertForbidden();
    }

    public function test_mis_pedidos_lo_abren_el_cliente_y_el_personal_pero_no_el_visitante(): void
    {
        $this->get('/mis-pedidos')->assertRedirect('/login');
        foreach (['cliente1', ...self::PERSONAL] as $clave) {
            $this->actingAs($this->usuario($clave))->get('/mis-pedidos')->assertOk();
        }
    }

    public function test_el_login_lleva_a_cada_rol_a_su_pagina_de_inicio(): void
    {
        // El logo del menú lleva a este mismo destino (frontend/src/lib/inicio.js); se verifica el espejo del backend.
        $destinos = ['administrador' => '/admin', 'cocinero' => '/comandas', 'cajero' => '/caja', 'mesero' => '/mesero/pedidos'];
        foreach ($destinos as $clave => $destino) {
            auth()->logout();
            $this->post('/login', ['email' => "$clave@example.test", 'password' => 'TrackEatDemo!2026'])->assertRedirect($destino);
        }
    }
}
