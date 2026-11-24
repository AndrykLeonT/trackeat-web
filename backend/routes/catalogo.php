<?php

use App\Http\Controllers\CatalogoController;
use App\Http\Middleware\ActiveTrackEatUser;
use App\Http\Middleware\CatalogAdmin;
use Illuminate\Support\Facades\Route;

Route::pattern('producto', '[0-9]{1,18}');
Route::pattern('categoria', '[0-9]{1,18}');

Route::get('/api/catalogo', [CatalogoController::class, 'publico']);
Route::get('/menu/{producto}', [CatalogoController::class, 'menu'])->whereNumber('producto');
Route::get('/catalogo/imagenes/{archivo}', [CatalogoController::class, 'imagen'])->where('archivo', '[a-f0-9-]{36}\.webp');
Route::prefix('admin')->middleware(['auth', ActiveTrackEatUser::class, CatalogAdmin::class])->group(function () {
    Route::get('productos', [CatalogoController::class, 'productos']);
    Route::get('productos/crear', [CatalogoController::class, 'formularioProducto']);
    Route::post('productos', [CatalogoController::class, 'guardarProducto'])->middleware('throttle:60,1');
    Route::get('productos/{producto}/editar', [CatalogoController::class, 'formularioProducto']);
    // POST supports multipart uploads reliably under PHP; existing admin forms use this endpoint.
    Route::post('productos/{producto}', [CatalogoController::class, 'guardarProducto'])->middleware('throttle:60,1');
    Route::delete('productos/{producto}', [CatalogoController::class, 'bajaProducto']);
    Route::post('productos/{producto}/restaurar', [CatalogoController::class, 'restaurarProducto']);
    Route::get('categorias', [CatalogoController::class, 'categorias']);
    Route::get('categorias/crear', [CatalogoController::class, 'formularioCategoria']);
    Route::post('categorias', [CatalogoController::class, 'guardarCategoria'])->middleware('throttle:60,1');
    Route::get('categorias/{categoria}/editar', [CatalogoController::class, 'formularioCategoria']);
    Route::post('categorias/{categoria}', [CatalogoController::class, 'guardarCategoria'])->middleware('throttle:60,1');
    Route::delete('categorias/{categoria}', [CatalogoController::class, 'bajaCategoria']);
    Route::post('categorias/{categoria}/restaurar', [CatalogoController::class, 'restaurarCategoria']);
});
