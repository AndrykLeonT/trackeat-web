<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Módulo 2 · Pedidos (T-05). Revierte el nullable de 2026_11_24_000001: `id_usuario` vuelve a ser obligatorio.
// El campo significa "quién levantó el pedido": un cliente (pedido hecho en la web) o el personal que lo captura
// (mesero, cajero o administrador). No existen pedidos sin dueño ni se vincula un cliente aparte.
// Si hubiera filas con `id_usuario` nulo, el ALTER falla en vez de borrar o inventar datos.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE pedidos ALTER COLUMN id_usuario SET NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE pedidos ALTER COLUMN id_usuario DROP NOT NULL');
    }
};
