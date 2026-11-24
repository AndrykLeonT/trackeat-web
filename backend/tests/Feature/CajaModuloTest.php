<?php

namespace Tests\Feature;

use App\Models\Corte;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\User;
use App\Services\CajaService;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

// Módulo 3 · Pagos y caja: las páginas y rutas HTTP. Las reglas de negocio de fondo
// (sobrepago, concurrencia, triggers) ya las cubren TrackEatTest y CajaConcurrencyTest.
class CajaModuloTest extends TestCase
{
    use RefreshDatabase;

    private User $cajero;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        $this->cajero = User::where('email', 'cajero@example.test')->firstOrFail();
        $this->admin = User::where('email', 'administrador@example.test')->firstOrFail();
    }

    private function pedido(string $numero): Pedido
    {
        return Pedido::where('numero_pedido', $numero)->firstOrFail();
    }

    private function abierto(): Corte
    {
        return Corte::where('estado', 'abierto')->firstOrFail();
    }

    public function test_solo_el_cajero_entra_a_la_caja(): void
    {
        $this->get('/caja')->assertRedirect('/login');
        foreach (['cliente1', 'cocinero', 'mesero'] as $quien) {
            $this->actingAs(User::where('email', "$quien@example.test")->first())->get('/caja')->assertForbidden();
        }
        $this->actingAs($this->admin)->get('/caja')->assertForbidden();
        $this->actingAs($this->admin)->get('/caja/cortes')->assertOk();
        $this->actingAs($this->cajero)->get('/admin/purga')->assertForbidden();
    }

    public function test_por_cobrar_muestra_solo_pedidos_con_saldo(): void
    {
        $this->actingAs($this->cajero)->get('/caja')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('caja/index', false)
            ->has('pedidos', 2)
            ->where('pedidos.0.numero_pedido', 'DEMO-001')
            ->where('pedidos.0.saldo', '25.00')
            ->where('pedidos.1.numero_pedido', 'DEMO-002')
            ->where('pedidos.1.pagado', '60.00')
            ->where('pedidos.1.saldo', '90.00')
            ->where('corte.id', $this->abierto()->id));
    }

    public function test_cada_cajero_trabaja_en_su_propio_turno(): void
    {
        $otro = (new User)->forceFill(['name' => 'Segundo Cajero', 'email' => 'segundo@example.test', 'password' => 'x', 'rol' => 'cajero']);
        $otro->save();
        $mio = $this->abierto();

        // Sin turno, el segundo cajero ve los pedidos pero no su corte; abre el suyo mientras el otro sigue abierto.
        $this->actingAs($otro)->get('/caja')->assertInertia(fn (Assert $page) => $page->where('corte', null)->has('pedidos', 2));
        $this->post('/caja/abrir', ['total_caja_inicial' => '150'])->assertRedirect('/caja');
        $suyo = Corte::where('id_usuario', $otro->id)->where('estado', 'abierto')->firstOrFail();
        $this->assertSame(2, Corte::where('estado', 'abierto')->count());

        // No abre otro sin hacer el corte del actual.
        $this->get('/caja/abrir')->assertRedirect('/caja/turno');
        $this->post('/caja/abrir', ['total_caja_inicial' => '50'])->assertSessionHasErrors('corte');

        // Cada quien cobra en su propio corte y ve solo sus pagos en el turno.
        $pedido = $this->pedido('DEMO-001');
        $this->post("/caja/pedidos/{$pedido->id}/cobrar", ['metodo_pago' => 'efectivo', 'monto' => '10'])->assertRedirect();
        $this->assertSame($suyo->id, Pago::where('id_pedido', $pedido->id)->value('id_corte'));
        $this->get('/caja/turno')->assertInertia(fn (Assert $page) => $page->where('corte.id', $suyo->id)->has('pagos', 1));
        $this->actingAs($this->cajero)->get('/caja/turno')->assertInertia(fn (Assert $page) => $page->where('corte.id', $mio->id)->has('pagos', 2));
    }

    public function test_pago_dividido_con_propina_deja_el_pedido_pagado(): void
    {
        $pedido = $this->pedido('DEMO-002');
        $this->actingAs($this->cajero)->get("/caja/pedidos/{$pedido->id}/cobrar")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('caja/cobrar', false)->where('pedido.saldo', '90.00')->has('pagos', 1));

        $this->post("/caja/pedidos/{$pedido->id}/cobrar", ['metodo_pago' => 'tarjeta', 'monto' => '60', 'propina' => '12.50'])
            ->assertRedirect()->assertSessionHas('status', 'Pago registrado.');
        $this->assertSame('30.00', $pedido->fresh()->saldo());

        $this->post("/caja/pedidos/{$pedido->id}/cobrar", ['metodo_pago' => 'transferencia', 'monto' => '30.00'])
            ->assertRedirect('/caja');
        $this->assertSame('pagado', $pedido->fresh()->estadoPago());
        $this->assertSame('12.50', Pago::where('id_pedido', $pedido->id)->where('metodo_pago', 'tarjeta')->value('propina'));
    }

    public function test_cobro_invalido_regresa_el_error_al_formulario(): void
    {
        $pedido = $this->pedido('DEMO-001');
        $this->actingAs($this->cajero);
        $this->post("/caja/pedidos/{$pedido->id}/cobrar", ['metodo_pago' => 'efectivo', 'monto' => '26'])->assertSessionHasErrors('monto');
        $this->post("/caja/pedidos/{$pedido->id}/cobrar", ['metodo_pago' => 'cheque', 'monto' => '5'])->assertSessionHasErrors('metodo_pago');
        $this->post("/caja/pedidos/{$pedido->id}/cobrar", ['metodo_pago' => 'efectivo', 'monto' => '5.123'])->assertSessionHasErrors('monto');
        $this->assertSame(0, Pago::where('id_pedido', $pedido->id)->count());
    }

    public function test_los_campos_de_dinero_solo_aceptan_numeros(): void
    {
        $pedido = $this->pedido('DEMO-001');
        $mensaje = 'Solo se aceptan números, con hasta dos decimales (por ejemplo 150.50).';
        $this->actingAs($this->cajero);
        foreach (['abc', '12a', '$50', '-5', '1,000', '5.123', '1e3'] as $malo) {
            $this->post("/caja/pedidos/{$pedido->id}/cobrar", ['metodo_pago' => 'efectivo', 'monto' => $malo])
                ->assertSessionHasErrors(['monto' => $mensaje]);
            $this->post("/caja/pedidos/{$pedido->id}/cobrar", ['metodo_pago' => 'efectivo', 'monto' => '5', 'propina' => $malo])
                ->assertSessionHasErrors(['propina' => $mensaje]);
            $this->post('/caja/cerrar', ['total_caja_final' => $malo, 'total_tarjeta_real' => '0', 'total_transferencia_real' => 'x'])
                ->assertSessionHasErrors(['total_caja_final' => $mensaje, 'total_transferencia_real' => $mensaje]);
        }
        $this->assertSame(0, Pago::where('id_pedido', $pedido->id)->count());
        $this->assertSame('abierto', $this->abierto()->estado);

        // Al abrir turno pasa lo mismo (el cajero necesita no tener uno abierto).
        $nuevo = (new User)->forceFill(['name' => 'Nuevo', 'email' => 'nuevo@example.test', 'password' => 'x', 'rol' => 'cajero']);
        $nuevo->save();
        $this->actingAs($nuevo)->post('/caja/abrir', ['total_caja_inicial' => '3OO'])->assertSessionHasErrors(['total_caja_inicial' => $mensaje]);
        $this->assertSame(0, Corte::where('id_usuario', $nuevo->id)->count());
    }

    public function test_eliminar_y_reembolsar_desde_el_turno(): void
    {
        $caja = app(CajaService::class);
        $pedido = $this->pedido('DEMO-001');
        $error = $caja->cobrar($this->cajero, $pedido->id, $this->abierto()->id, 'efectivo', '25');
        $this->actingAs($this->cajero)->post("/caja/pagos/{$error->id}/eliminar")->assertRedirect();
        $this->assertTrue($error->fresh()->eliminado);

        $bueno = $caja->cobrar($this->cajero, $pedido->id, $this->abierto()->id, 'tarjeta', '25');
        $this->post("/caja/pagos/{$bueno->id}/reembolsar")->assertRedirect();
        $this->assertTrue($bueno->fresh()->reembolso);
        $this->post("/caja/pagos/{$bueno->id}/eliminar")->assertSessionHasErrors('pago');

        $this->get('/caja/turno')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('caja/turno', false)
            ->where('resumen.total_efectivo', '60.00')
            ->where('resumen.total_tarjeta', '0.00')
            ->has('pagos', 4));
    }

    public function test_no_se_abre_un_segundo_turno(): void
    {
        $this->actingAs($this->cajero)->get('/caja/abrir')->assertRedirect('/caja/turno');
        $this->post('/caja/abrir', ['total_caja_inicial' => '100'])->assertSessionHasErrors('corte');
        $this->assertSame(1, Corte::where('estado', 'abierto')->count());
    }

    public function test_cerrar_congela_el_corte_y_muestra_diferencias(): void
    {
        $corte = $this->abierto();
        // Sistema: fondo 300 + efectivo 60 = 360 esperados; se cuentan 350 → faltan 10.
        $this->actingAs($this->cajero)->post('/caja/cerrar', [
            'total_caja_final' => '350', 'total_tarjeta_real' => '0', 'total_transferencia_real' => '0',
        ])->assertRedirect("/caja/cortes/{$corte->id}");

        $this->assertSame('cerrado', $corte->fresh()->estado);
        $this->get("/caja/cortes/{$corte->id}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('caja/corte', false)
            ->where('resumen.diferencia_efectivo', '10.00')
            ->where('resumen.total_sistema', '60.00'));

        // Sin turno, las páginas del turno mandan a abrir uno; luego se puede abrir el siguiente.
        $this->get('/caja/turno')->assertRedirect('/caja/abrir');
        $this->post('/caja/abrir', ['total_caja_inicial' => '200.00'])->assertRedirect('/caja');
        $this->assertSame(1, Corte::where('estado', 'abierto')->count());
    }

    public function test_historial_de_cortes_por_rol(): void
    {
        $this->actingAs($this->cajero)->get('/caja/cortes')->assertInertia(fn (Assert $page) => $page
            ->component('caja/cortes', false)->has('cortes', 1));
        $otro = (new User)->forceFill(['name' => 'Otro Cajero', 'email' => 'otro@example.test', 'password' => 'x', 'rol' => 'cajero']);
        $otro->save();
        $cerrado = Corte::where('estado', 'cerrado')->firstOrFail();
        $this->actingAs($otro)->get('/caja/cortes')->assertInertia(fn (Assert $page) => $page->has('cortes', 0));
        $this->actingAs($otro)->get("/caja/cortes/{$cerrado->id}")->assertForbidden();
        $this->actingAs($this->admin)->get("/caja/cortes/{$cerrado->id}")->assertOk();
    }

    public function test_purga_pide_contrasena_y_borra_el_pago_eliminado(): void
    {
        $caja = app(CajaService::class);
        $pago = $caja->cobrar($this->cajero, $this->pedido('DEMO-001')->id, $this->abierto()->id, 'efectivo', '10');
        $caja->corregir($this->cajero, $pago->id);

        $this->actingAs($this->admin)->get('/admin/purga')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('caja/purga', false)->has('pagos', 1));
        $this->delete("/admin/purga/pagos/{$pago->id}", ['password' => 'incorrecta'])->assertSessionHasErrors('password');
        $this->assertNotNull(Pago::find($pago->id));
        $this->delete("/admin/purga/pagos/{$pago->id}", ['password' => 'TrackEatDemo!2026'])->assertSessionHas('status');
        $this->assertNull(Pago::find($pago->id));

        $this->actingAs($this->cajero)->delete("/admin/purga/pagos/{$pago->id}", ['password' => 'TrackEatDemo!2026'])->assertNotFound();
    }
}
