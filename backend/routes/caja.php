<?php

// Módulo 3 · Pagos y caja. Las reglas de cobro viven en CajaService y PurgaService;
// aquí solo se exponen como páginas para el cajero y el administrador.

use App\Http\Controllers\Caja\CajaController;
use App\Http\Controllers\Caja\CorteController;
use App\Http\Controllers\Caja\PagoController;
use App\Http\Controllers\Caja\PurgaController;
use App\Http\Middleware\ActiveTrackEatUser;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', ActiveTrackEatUser::class])->group(function () {
    Route::prefix('caja')->name('caja.')->group(function () {
        Route::get('/', [CajaController::class, 'index'])->name('index');

        Route::get('abrir', [CorteController::class, 'create'])->name('abrir');
        Route::post('abrir', [CorteController::class, 'store'])->middleware('throttle:60,1')->name('abrir.store');
        Route::get('turno', [CorteController::class, 'turno'])->name('turno');
        Route::get('cerrar', [CorteController::class, 'cerrarForm'])->name('cerrar');
        Route::post('cerrar', [CorteController::class, 'cerrar'])->middleware('throttle:60,1')->name('cerrar.store');
        Route::get('cortes', [CorteController::class, 'index'])->name('cortes');
        Route::get('cortes/{corte}', [CorteController::class, 'show'])->name('cortes.show');

        Route::get('pedidos/{pedido}/cobrar', [PagoController::class, 'create'])->name('cobrar');
        Route::post('pedidos/{pedido}/cobrar', [PagoController::class, 'store'])->middleware('throttle:60,1')->name('cobrar.store');
        Route::post('pagos/{pago}/eliminar', [PagoController::class, 'eliminar'])->middleware('throttle:60,1')->name('pagos.eliminar');
        Route::post('pagos/{pago}/reembolsar', [PagoController::class, 'reembolsar'])->middleware('throttle:60,1')->name('pagos.reembolsar');
    });

    Route::get('admin/purga', [PurgaController::class, 'index'])->name('admin.purga');
    Route::delete('admin/purga/pagos/{pago}', [PurgaController::class, 'destroy'])->middleware('throttle:30,1')->name('admin.purga.destroy');
});
