<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Módulo 2 · Comandas (T-03). Todo cocinero tiene estación: si al crearlo (o al cambiarle el rol a cocinero)
// no se indica una, la base le asigna `general` (ve y avanza cocina y barra). Si se indica `cocina` o `barra`,
// se respeta. La regla vive en PostgreSQL para que se cumpla sin importar qué pantalla o módulo cree al usuario.
// Los cocineros que ya existían sin estación pasan a `general`.
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
UPDATE users SET estacion = 'general' WHERE rol = 'cocinero' AND estacion IS NULL;

CREATE OR REPLACE FUNCTION trackeat_cocinero_estacion() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.rol = 'cocinero' AND NEW.estacion IS NULL THEN
        NEW.estacion := 'general';
    END IF;
    RETURN NEW;
END;
$$;
CREATE TRIGGER cocinero_estacion BEFORE INSERT OR UPDATE OF rol, estacion ON users
FOR EACH ROW EXECUTE FUNCTION trackeat_cocinero_estacion();

ALTER TABLE users ADD CONSTRAINT users_cocinero_con_estacion_check CHECK (rol <> 'cocinero' OR estacion IS NOT NULL);
SQL);
    }

    public function down(): void
    {
        // Los cocineros conservan la estación que ya tienen; solo se retira la regla.
        DB::unprepared(<<<'SQL'
ALTER TABLE users DROP CONSTRAINT users_cocinero_con_estacion_check;
DROP TRIGGER cocinero_estacion ON users;
DROP FUNCTION trackeat_cocinero_estacion();
SQL);
    }
};
