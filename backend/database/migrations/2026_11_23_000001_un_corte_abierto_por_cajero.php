<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Cada cajero tiene su propio corte abierto (antes era uno solo para todo el restaurante).
// Un cajero no puede abrir otro turno hasta cerrar el suyo; la base lo impide aunque se salte el servicio.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS cortes_una_caja_abierta');
        DB::statement("CREATE UNIQUE INDEX cortes_un_abierto_por_cajero ON cortes (id_usuario) WHERE estado = 'abierto'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS cortes_un_abierto_por_cajero');
        DB::statement("CREATE UNIQUE INDEX cortes_una_caja_abierta ON cortes ((1)) WHERE estado = 'abierto'");
    }
};
