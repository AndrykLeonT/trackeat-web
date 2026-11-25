<?php

namespace App\Support;

// Folio visible de un pedido: `TE-20261125-006` se muestra como `#006`. Es el espejo en PHP de
// `formatoFolio` (frontend/src/lib/folio.js). Los folios anteriores (`TE-` más un código largo,
// `DEMO-001`) se muestran tal cual. Solo es para presentar: en la base y en la API el folio va completo.
final class Folio
{
    public static function visible(?string $numero): string
    {
        if ($numero !== null && preg_match('/^TE-\d{8}-(\d+)$/D', $numero, $partes)) {
            return '#'.$partes[1];
        }

        return $numero ?? '';
    }
}
