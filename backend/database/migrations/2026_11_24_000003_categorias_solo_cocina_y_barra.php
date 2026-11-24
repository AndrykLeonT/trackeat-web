<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Módulo 2 · Comandas (T-03b). Las categorías (y por herencia sus productos) solo se preparan en
// `cocina` o `barra`. `general` deja de ser una estación de producto: pasa a ser la estación de un
// cocinero que ve y atiende todas las comandas (`users.estacion`, ver 2026_11_24_000002).
//
// Las categorías que hoy sean `general` se reclasifican por su nombre: bebidas a `barra`, el resto a `cocina`.
// No se tocan `pedidos_estacion`, `items_pedido` ni `historial_estados`: pueden tener filas históricas con
// `general` (y `items_pedido` es inmutable por trigger); simplemente ya no se crean filas nuevas.
return new class extends Migration
{
    // Palabras completas que identifican una categoría de bebidas ("Aguacates" no cuenta, "Aguas frescas" sí).
    // Se delimitan con separadores en vez de `\y`: este depende del locale de la base y en `C` no trata la `é` como letra.
    private const BEBIDAS = '(^|[^[:alnum:]])(bebidas?|jugos?|refrescos?|caf[eé]s?|t[eé]s?|licuados?|aguas?|cervezas?|vinos?|licores?|limonadas?|sodas?|malteadas?|batidos?|c[oó]cteles?|smoothies?)([^[:alnum:]]|$)';

    public function up(): void
    {
        DB::statement('ALTER TABLE categorias DROP CONSTRAINT categorias_check_0');
        // La expresión va como parámetro: contiene `?` y no debe confundirse con un marcador.
        DB::update("UPDATE categorias SET estacion = CASE WHEN nombre ~* ? THEN 'barra' ELSE 'cocina' END WHERE estacion = 'general'", [self::BEBIDAS]);
        DB::statement("ALTER TABLE categorias ADD CONSTRAINT categorias_estacion_check CHECK (estacion IN ('cocina','barra'))");
    }

    public function down(): void
    {
        // La reclasificación de datos no se revierte: las categorías conservan `cocina` o `barra`.
        DB::statement('ALTER TABLE categorias DROP CONSTRAINT categorias_estacion_check');
        DB::statement("ALTER TABLE categorias ADD CONSTRAINT categorias_check_0 CHECK (estacion IN ('general','cocina','barra'))");
    }
};
