<?php

// Módulo 2 · Pedidos y comandas. La lógica vive en PedidoService; aquí solo se exponen las rutas.

use App\Http\Controllers\Pedidos\PedidoController;
use App\Http\Middleware\ActiveTrackEatUser;
use Illuminate\Support\Facades\Route;

// Patrón global: también lo usan las rutas de caja (`caja/pedidos/{pedido}/cobrar`),
// así que este archivo debe incluirse en web.php antes que caja.php.
Route::pattern('pedido', '[0-9]{1,18}');

Route::prefix('api')->middleware(['auth', ActiveTrackEatUser::class])->group(function () {
    Route::get('pedidos', [PedidoController::class, 'pedidos']);
    Route::get('pedidos/{pedido}', [PedidoController::class, 'detalle']);
    Route::post('pedidos', [PedidoController::class, 'crear'])->middleware('throttle:60,1');
    Route::patch('pedidos/{pedido}/estacion', [PedidoController::class, 'avanzar'])->middleware('throttle:120,1');
    Route::get('tablero', [PedidoController::class, 'tablero']);
});

Route::get('/pedidos/{pedido}', [PedidoController::class, 'paginaPedido'])->middleware(['auth', ActiveTrackEatUser::class]);
