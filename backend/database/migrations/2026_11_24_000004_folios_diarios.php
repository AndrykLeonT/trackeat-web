<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Módulo 2 · Pedidos (T-04). Contador del folio diario `TE-AAAAMMDD-NNN`: una fila por día con el último
// número asignado. Vive en PostgreSQL (no en memoria de PHP) para que dos nodos nunca repitan un folio.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('folios_diarios', function (Blueprint $table) {
            $table->date('fecha')->primary();
            $table->unsignedInteger('ultimo')->default(0);
        });
        DB::statement('ALTER TABLE folios_diarios ADD CONSTRAINT folios_diarios_ultimo_check CHECK (ultimo >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('folios_diarios');
    }
};
