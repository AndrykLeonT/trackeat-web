<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Módulo 2 · Comandas (T-03). Estación que atiende un cocinero: la asigna el administrador y
// decide qué comandas ve y avanza. Es un dato privilegiado, igual que `rol`: no va en $fillable.
// El valor nulo (sin estación asignada) pasa el CHECK.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('estacion', 20)->nullable();
        });
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_estacion_check CHECK (estacion IN ('general','cocina','barra'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_estacion_check');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('estacion'));
    }
};
