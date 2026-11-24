<?php

use App\Models\User;
use App\Services\PedidoService;
use Illuminate\Contracts\Console\Kernel;

// Proceso independiente para probar que el folio diario no se repite con creaciones simultáneas.
// Argumentos: nombre, id del cliente, id del producto, cantidad de pedidos, instante de arranque (epoch).
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || ! str_ends_with(config('database.connections.pgsql.database'), '_test')) {
    throw new RuntimeException('Este proceso solo opera sobre una base de pruebas.');
}
$cliente = User::findOrFail((int) $argv[2]);
$service = app(PedidoService::class);
// Todos los procesos arrancan a la vez para que sus creaciones realmente compitan.
while (microtime(true) < (float) $argv[5]) {
    usleep(1000);
}
$folios = [];
for ($i = 0; $i < (int) $argv[4]; $i++) {
    $folios[] = $service->crear($cliente, ['tipo_entrega' => 'para_llevar',
        'items' => [['id_producto' => (int) $argv[3], 'cantidad' => 1]]])->numero_pedido;
}
echo json_encode($folios);
