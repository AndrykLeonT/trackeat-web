<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION catalogo_nombre_key(text) RETURNS text LANGUAGE sql IMMUTABLE STRICT AS $$
SELECT lower(regexp_replace(normalize(btrim($1), NFD), U&'[\0300-\036f]', '', 'g'))
$$
SQL);
        foreach (['categorias' => 'catalogo_nombre_key(nombre)', 'productos' => 'id_categoria, catalogo_nombre_key(nombre)'] as $table => $group) {
            if (DB::select("SELECT 1 FROM $table GROUP BY $group HAVING count(*) > 1 LIMIT 1")) {
                throw new RuntimeException("Hay nombres duplicados en $table. Revise los registros antes de migrar; no se renombró ni eliminó ninguno.");
            }
        }
        DB::statement('CREATE UNIQUE INDEX categorias_nombre_catalogo_unique ON categorias (catalogo_nombre_key(nombre))');
        DB::statement('CREATE UNIQUE INDEX productos_nombre_catalogo_unique ON productos (id_categoria, catalogo_nombre_key(nombre))');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX productos_nombre_catalogo_unique');
        DB::statement('DROP INDEX categorias_nombre_catalogo_unique');
        DB::statement('DROP FUNCTION catalogo_nombre_key(text)');
    }
};
