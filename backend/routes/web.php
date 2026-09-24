<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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
    return Inertia::render('public/dashboard', [
        'title' => 'Comandas',
        'description' => 'Gestión y estado de órdenes de cocina en tiempo real.',
    ]);
})->name('comandas');

Route::get('/menu', function () {
    return Inertia::render('public/menu');
})->name('menu');

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
