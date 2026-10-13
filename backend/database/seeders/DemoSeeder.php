<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Corte;
use App\Models\Pedido;
use App\Models\User;
use App\Services\CajaService;
use App\Services\PedidoService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Los ejemplos solo se cargan en local/testing.');
        }
        DB::transaction(function () {
            DB::select('SELECT pg_advisory_xact_lock(742001)');
            $numbers = ['DEMO-001', 'DEMO-002', 'DEMO-003', 'DEMO-004'];
            $existing = Pedido::whereIn('numero_pedido', $numbers)->count();
            if ($existing === 4) {
                return;
            }
            if ($existing !== 0 || User::whereIn('email', array_map(fn ($key) => "$key@example.test", [
                'cliente1', 'cliente2', 'administrador', 'cocinero', 'cajero', 'mesero',
            ]))->exists() || Corte::where('estado', 'abierto')->exists()) {
                throw new RuntimeException('Hay un corte abierto o identificadores de ejemplo ocupados. No se modificaron datos.');
            }
            $users = [];
            foreach (['cliente1' => 'cliente', 'cliente2' => 'cliente', 'administrador' => 'administrador',
                'cocinero' => 'cocinero', 'cajero' => 'cajero', 'mesero' => 'mesero'] as $key => $role) {
                $users[$key] = (new User)->forceFill(['name' => ucfirst($key).' Demo', 'primer_nombre' => ucfirst($key),
                    'primer_apellido' => 'Demo', 'email' => "$key@example.test", 'password' => 'TrackEatDemo!2026',
                    'rol' => $role, 'email_verified_at' => now()]);
                $users[$key]->save();
            }
            $products = [];
            foreach ([['Hamburguesas', 'cocina', [['Hamburguesa', '120.00'], ['Papas', '50.00']]],
                ['Bebidas', 'barra', [['Limonada', '30.00']]], ['Empaquetados', 'general', [['Snack', '25.00']]]] as [$name, $station, $items]) {
                $category = Categoria::create(['nombre' => $name, 'estacion' => $station]);
                foreach ($items as [$product, $price]) {
                    $products[$product] = $category->productos()->create(['nombre' => $product, 'precio' => $price,
                        'descripcion' => 'Producto ficticio para verificar TrackEat.']);
                    $products[$product]->imagenes()->create(['url' => '/logo.svg', 'orden' => 0]);
                }
            }
            $orders = app(PedidoService::class);
            $cash = app(CajaService::class);
            $make = function (string $number, User $client, array $items, string $type = 'para_llevar', ?int $mesa = null) use ($orders, $users, $products) {
                $order = $orders->crear($users['mesero'], ['id_usuario' => $client->id, 'tipo_entrega' => $type, 'num_mesa' => $mesa,
                    'items' => array_map(fn ($name) => ['id_producto' => $products[$name]->id, 'cantidad' => 1,
                        'notas' => $name === 'Hamburguesa' ? 'sin cebolla' : null], $items)]);
                $order->update(['numero_pedido' => $number]);

                return $order;
            };
            $closed = $cash->abrir($users['cajero'], '500.00');
            $delivered = $make('DEMO-003', $users['cliente1'], ['Hamburguesa', 'Limonada'], 'comer_aqui', 4);
            foreach (['cocina', 'barra'] as $station) {
                $orders->avanzar($users['cocinero'], $delivered->id, $station, 'preparacion');
                $orders->avanzar($users['cocinero'], $delivered->id, $station, 'listo');
                $orders->avanzar($users['mesero'], $delivered->id, $station, 'entregado');
            }
            $cash->cobrar($users['cajero'], $delivered->id, $closed->id, 'efectivo', '100.00');
            $cash->cobrar($users['cajero'], $delivered->id, $closed->id, 'tarjeta', '50.00', '10.00');
            $cash->cerrar($users['cajero'], $closed->id, '600.00', '50.00', '0.00');
            $open = $cash->abrir($users['cajero'], '300.00');
            $make('DEMO-001', $users['cliente2'], ['Snack']);
            $preparing = $make('DEMO-002', $users['cliente1'], ['Hamburguesa', 'Limonada']);
            $orders->avanzar($users['cocinero'], $preparing->id, 'cocina', 'preparacion');
            $orders->avanzar($users['cocinero'], $preparing->id, 'barra', 'preparacion');
            $cash->cobrar($users['cajero'], $preparing->id, $open->id, 'efectivo', '60.00');
            $cancelled = $make('DEMO-004', $users['cliente2'], ['Papas']);
            $refund = $cash->cobrar($users['cajero'], $cancelled->id, $open->id, 'transferencia', '50.00');
            $cash->reembolsar($users['cajero'], $refund->id);
            $orders->cancelar($users['mesero'], $cancelled->id);
        }, 3);
    }
}
