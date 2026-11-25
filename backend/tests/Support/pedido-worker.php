<?php

use App\Models\User;
use App\Services\PedidoService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

// Proceso independiente para probar creaciones simultáneas de pedidos (folio diario y límite de pedidos en curso).
// Argumentos: nombre, id del usuario, id del producto, cantidad de pedidos, instante de arranque (epoch).
// Imprime un JSON con el folio de cada pedido creado, o null si el servidor lo rechazó por validación.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || ! str_ends_with(config('database.connections.pgsql.database'), '_test')) {
    throw new RuntimeException('Este proceso solo opera sobre una base de pruebas.');
}
$usuario = User::findOrFail((int) $argv[2]);
$service = app(PedidoService::class);
// Todos los procesos arrancan a la vez para que sus creaciones realmente compitan.
while (microtime(true) < (float) $argv[5]) {
    usleep(1000);
}
$folios = [];
for ($i = 0; $i < (int) $argv[4]; $i++) {
    try {
        $folios[] = $service->crear($usuario, ['tipo_entrega' => 'para_llevar',
            'items' => [['id_producto' => (int) $argv[3], 'cantidad' => 1]]])->numero_pedido;
    } catch (ValidationException) {
        $folios[] = null;
    }
}
echo json_encode($folios);
