<?php

// Módulo 2 · Pedidos y comandas. La lógica vive en PedidoService; aquí solo se exponen las rutas.

use App\Http\Controllers\Pedidos\AdminPedidoController;
use App\Http\Controllers\Pedidos\PedidoController;
use App\Http\Middleware\ActiveTrackEatUser;
use App\Http\Middleware\RequireRole;
use Illuminate\Support\Facades\Route;

// Patrón global: también lo usan las rutas de caja (`caja/pedidos/{pedido}/cobrar`),
// así que este archivo debe incluirse en web.php antes que caja.php.
Route::pattern('pedido', '[0-9]{1,18}');

Route::prefix('api')->middleware(['auth', ActiveTrackEatUser::class])->group(function () {
    Route::get('pedidos', [PedidoController::class, 'pedidos']);
    Route::get('pedidos/{pedido}', [PedidoController::class, 'detalle']);
    Route::post('pedidos', [PedidoController::class, 'crear'])->middleware('throttle:60,1');
    Route::patch('pedidos/{pedido}/estacion', [PedidoController::class, 'avanzar'])->middleware('throttle:120,1');
    Route::post('pedidos/{pedido}/cancelar', [PedidoController::class, 'cancelar'])->middleware('throttle:30,1');
    Route::post('pedidos/{pedido}/entregar', [PedidoController::class, 'entregar'])->middleware('throttle:120,1');
    Route::get('mesero/pedidos', [PedidoController::class, 'porEntregar']);
    Route::get('tablero', [PedidoController::class, 'tablero']);
    Route::get('comandas', [PedidoController::class, 'comandas']);
});

Route::get('/pedidos/{pedido}', [PedidoController::class, 'paginaPedido'])->middleware(['auth', ActiveTrackEatUser::class]);
Route::get('/mesero/pedidos', [PedidoController::class, 'paginaPorEntregar'])->middleware(['auth', ActiveTrackEatUser::class])->name('mesero.pedidos');
Route::get('/mis-pedidos',[PedidoController::class, 'misPedidos'])->middleware(['auth', ActiveTrackEatUser::class])->name('mis-pedidos');

// HU-10 · Administración de pedidos (solo administrador): lista con filtros y detalle con bitácora.
Route::prefix('admin')->middleware(['auth', ActiveTrackEatUser::class, RequireRole::class.':administrador'])->group(function () {
    Route::get('pedidos', [AdminPedidoController::class, 'index'])->name('admin.pedidos');
    Route::get('pedidos/{pedido}', [AdminPedidoController::class, 'show'])->name('admin.pedidos.show');
});
