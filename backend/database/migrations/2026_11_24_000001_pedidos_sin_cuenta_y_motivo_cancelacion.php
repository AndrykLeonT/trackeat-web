<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Módulo 2 · Pedidos. Mesero y cajero pueden capturar pedidos sin cuenta de cliente
// (mesa o teléfono), por eso `id_usuario` admite nulo. La llave foránea y el índice se conservan.
// `motivo_cancelacion` guarda el motivo elegido al cancelar (HU-05).
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE pedidos ALTER COLUMN id_usuario DROP NOT NULL');
        Schema::table('pedidos', function (Blueprint $table) {
            $table->string('motivo_cancelacion', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', fn (Blueprint $table) => $table->dropColumn('motivo_cancelacion'));
        // Falla a propósito si ya existen pedidos sin cuenta: no se borran datos en silencio.
        DB::statement('ALTER TABLE pedidos ALTER COLUMN id_usuario SET NOT NULL');
    }
};
