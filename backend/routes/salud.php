<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// Fuera del grupo web: el balanceador la consulta seguido y no debe crear sesiones.
Route::get('/salud', function () {
    $nodo = (string) config('trackeat.nodo');

    try {
        DB::select('select 1');
    } catch (Throwable) {
        return response()->json(['nodo' => $nodo, 'bd' => 'caida'], 503);
    }

    return response()->json([
        'nodo' => $nodo,
        'bd' => 'ok',
        'ip_cliente' => request()->ip(),
    ]);
})->name('salud');
