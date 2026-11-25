<?php

use App\Http\Controllers\DemoController;
use App\Http\Controllers\TrackEatController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\RedirectResponse;

Route::get('/', function () {
    return Inertia::render('public/landing');
})->name('home');

Route::get('/dashboard', function () {
    return Inertia::render('public/dashboard', [
        'title' => 'Dashboard',
        'description' => 'Visión general de pedidos y estado de cocina.',
    ]);
})->name('dashboard');

Route::get('/comandas', function () {
    return Inertia::render('public/comandas');
})->name('comandas');

Route::get('/comandas/desglose', function () {
    return Inertia::render('public/desglose-comandas');
})->name('comandas.desglose');

// Keep the trailing slash: the static shell uses relative asset URLs.
Route::get('/menu', fn () => new RedirectResponse('/menu/'))->name('menu');

Route::get('/demo/{room}', [DemoController::class, 'enter'])->middleware(['signed', 'throttle:120,1'])->name('demo.enter');
Route::get('/api/sesion', [TrackEatController::class, 'sesion']);

// El catálogo tiene su propio HTML estático para poder recargarlo sin conexión.
Route::get('/personal', fn () => new RedirectResponse('/personal/'))->name('personal');

Route::get('/recetas', function () {
    return Inertia::render('public/dashboard', [
        'title' => 'Recetas',
        'description' => 'Fichas técnicas, ingredientes y costos unitarios de cocina.',
    ]);
})->name('recetas');

Route::get('/metricas', function () {
    return Inertia::render('public/dashboard', [
        'title' => 'Métricas KDS',
        'description' => 'Tiempos de preparación, rotación de pedidos y optimización de cocina (-35%).',
    ]);
})->name('metricas');

require __DIR__.'/auth.php';
require __DIR__.'/catalogo.php';
// pedidos.php va antes que caja.php: define el patrón global `{pedido}` que caja también usa.
require __DIR__.'/pedidos.php';
require __DIR__.'/caja.php';
