<?php

namespace Tests\Feature;

use App\Events\PedidoActualizado;
use App\Models\Pedido;
use Database\Seeders\DemoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Módulo 2 · Pedidos y comandas. T-08 completa este archivo con el resto de reglas del módulo.
class PedidosModuloTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    private function sinCuenta(): Pedido
    {
        return Pedido::create(['id_usuario' => null, 'numero_pedido' => 'TE-SIN-CUENTA-1',
            'tipo_entrega' => 'comer_aqui', 'num_mesa' => 4, 'total' => '10.00']);
    }

    public function test_un_pedido_puede_guardarse_sin_cuenta_y_la_llave_foranea_sigue_vigente(): void
    {
        $pedido = $this->sinCuenta()->fresh();
        $this->assertNull($pedido->id_usuario);
        $this->assertNull($pedido->usuario);

        try {
            DB::transaction(fn () => Pedido::where('numero_pedido', 'DEMO-001')->update(['id_usuario' => 99999]));
            $this->fail('Un cliente inexistente debe seguir siendo rechazado.');
        } catch (QueryException $error) {
            $this->assertSame('23503', $error->getCode());
        }
    }

    public function test_el_motivo_de_cancelacion_se_guarda(): void
    {
        $pedido = $this->sinCuenta();
        $this->assertNull($pedido->fresh()->motivo_cancelacion);
        $pedido->update(['motivo_cancelacion' => 'error_de_captura']);
        $this->assertSame('error_de_captura', $pedido->fresh()->motivo_cancelacion);
    }

    public function test_el_evento_de_un_pedido_sin_cuenta_no_emite_al_canal_de_usuario(): void
    {
        $sinCuenta = array_map(fn ($canal) => $canal->name, PedidoActualizado::forOrder($this->sinCuenta()->fresh())->broadcastOn());
        $this->assertSame(['private-trackeat.personal'], $sinCuenta);

        $conCuenta = Pedido::where('numero_pedido', 'DEMO-001')->firstOrFail();
        $canales = array_map(fn ($canal) => $canal->name, PedidoActualizado::forOrder($conCuenta)->broadcastOn());
        $this->assertContains('private-trackeat.usuario.'.$conCuenta->id_usuario, $canales);
    }
}
