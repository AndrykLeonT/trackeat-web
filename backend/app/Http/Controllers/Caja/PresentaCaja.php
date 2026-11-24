<?php

namespace App\Http\Controllers\Caja;

use App\Models\Corte;
use App\Models\Pago;
use App\Models\User;

// Formato común de cortes y pagos para las páginas de caja. Los montos viajan como texto
// ("60.00") para que el navegador nunca los convierta en números de punto flotante.
trait PresentaCaja
{
    // Campos de dinero: solo números con hasta dos decimales, igual que App\Support\Money::parse.
    private function reglaDinero(bool $obligatorio = true): array
    {
        return [$obligatorio ? 'required' : 'nullable', 'string', 'regex:/^\d{1,10}(\.\d{1,2})?$/'];
    }

    private function mensajesDinero(array $campos): array
    {
        $mensajes = [];
        foreach ($campos as $campo) {
            $mensajes["$campo.regex"] = 'Solo se aceptan números, con hasta dos decimales (por ejemplo 150.50).';
            $mensajes["$campo.string"] = 'Solo se aceptan números, con hasta dos decimales (por ejemplo 150.50).';
        }

        return $mensajes;
    }

    // Cada cajero trabaja sobre su propio corte abierto.
    private function corteDe(User $cajero): ?Corte
    {
        return Corte::activos()->where('estado', 'abierto')->where('id_usuario', $cajero->id)->first();
    }

    private function corte(Corte $corte): array
    {
        return [
            'id' => $corte->id,
            'estado' => $corte->estado,
            'cajero' => $corte->usuario?->name,
            'fecha_apertura' => $corte->fecha_apertura?->toIso8601String(),
            'fecha_cierre' => $corte->fecha_cierre?->toIso8601String(),
            'total_caja_inicial' => $corte->total_caja_inicial,
            'total_caja_final' => $corte->total_caja_final,
            'total_tarjeta_real' => $corte->total_tarjeta_real,
            'total_transferencia_real' => $corte->total_transferencia_real,
            'total_real' => $corte->total_real,
        ];
    }

    private function pago(Pago $pago): array
    {
        return [
            'id' => $pago->id,
            'pedido' => $pago->pedido?->numero_pedido,
            'id_pedido' => $pago->id_pedido,
            'metodo_pago' => $pago->metodo_pago,
            'monto' => $pago->monto,
            'propina' => $pago->propina,
            'estado' => $pago->eliminado ? 'eliminado' : ($pago->reembolso ? 'reembolsado' : 'activo'),
            'cajero' => $pago->usuario?->name,
            'fecha' => $pago->created_at?->toIso8601String(),
        ];
    }
}
