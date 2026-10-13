<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException('TrackEat requiere PostgreSQL. Configure DB_CONNECTION=pgsql.');
        }
        Schema::table('users', function (Blueprint $table) {
            $table->string('primer_nombre')->nullable();
            $table->string('segundo_nombre')->nullable();
            $table->string('primer_apellido')->nullable();
            $table->string('segundo_apellido')->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('rol', 20)->default('cliente');
            $table->boolean('eliminado')->default(false);
        });
        // No inventar apellidos al migrar las cuentas anteriores.
        DB::statement('UPDATE users SET primer_nombre = name');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_rol_check CHECK (rol IN ('cliente','cocinero','cajero','mesero','administrador'))");
        // Falla sin modificar datos si ya existen correos que solo difieren por mayúsculas.
        DB::statement('CREATE UNIQUE INDEX users_email_normalizado_unique ON users (lower(email))');
        DB::statement('UPDATE users SET email = lower(email)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX users_email_normalizado_unique');
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_rol_check');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn([
            'primer_nombre', 'segundo_nombre', 'primer_apellido', 'segundo_apellido', 'telefono', 'rol', 'eliminado',
        ]));
    }
};
