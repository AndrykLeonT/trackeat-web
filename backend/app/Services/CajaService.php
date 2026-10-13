<?php

namespace App\Services;

use App\Models\Corte;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\User;
use App\Support\DomainAccess;
use App\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CajaService
{
    public function abrir(User $actor, mixed $fondo): Corte
    {
        DomainAccess::requireRole($actor, ['cajero']);
        $fondo = Money::parse($fondo, 'total_caja_inicial');

        return DB::transaction(function () use ($actor, $fondo) {
            // También serializa cuando todavía no existe ninguna fila de corte.
            DB::select('SELECT pg_advisory_xact_lock(742001)');
            if (Corte::where('estado', 'abierto')->exists()) {
                $this->invalid('corte', 'Debe cerrar el corte actual antes de abrir otro turno.');
            }

            return Corte::create(['id_usuario' => $actor->id, 'fecha_apertura' => now(), 'total_caja_inicial' => $fondo])->refresh();
        }, 3);
    }

    public function cobrar(User $actor, int $pedidoId, int $corteId, string $metodo, mixed $monto, mixed $propina = '0'): Pago
    {
        DomainAccess::requireRole($actor, ['cajero']);
        $monto = Money::parse($monto);
        $propina = Money::parse($propina, 'propina');
        if (! in_array($metodo, ['efectivo', 'tarjeta', 'transferencia'], true) || bccomp($monto, '0', 2) <= 0) {
            $this->invalid('pago', 'Método inválido o importe no positivo.');
        }

        return DB::transaction(function () use ($actor, $pedidoId, $corteId, $metodo, $monto, $propina) {
            $pedido = Pedido::lockForUpdate()->findOrFail($pedidoId);
            $this->openOwned($actor, $corteId);
            if ($pedido->eliminado || $pedido->estado === 'cancelado') {
                $this->invalid('pedido', 'No puede cobrar un pedido cancelado o eliminado.');
            }
            if (bccomp($monto, $pedido->saldo(), 2) > 0) {
                $this->invalid('monto', 'El cobro supera el saldo pendiente.');
            }

            return Pago::create(['id_pedido' => $pedido->id, 'id_usuario' => $actor->id, 'id_corte' => $corteId,
                'metodo_pago' => $metodo, 'monto' => $monto, 'propina' => $propina]);
        }, 3);
    }

    public function corregir(User $actor, int $pagoId): Pago
    {
        return $this->invalidate($actor, $pagoId, 'eliminado');
    }

    public function reembolsar(User $actor, int $pagoId): Pago
    {
        return $this->invalidate($actor, $pagoId, 'reembolso');
    }

    private function invalidate(User $actor, int $pagoId, string $flag): Pago
    {
        DomainAccess::requireRole($actor, ['cajero']);

        return DB::transaction(function () use ($actor, $pagoId, $flag) {
            $reference = Pago::findOrFail($pagoId);
            Pedido::lockForUpdate()->findOrFail($reference->id_pedido);
            $this->openOwned($actor, $reference->id_corte);
            $pago = Pago::lockForUpdate()->findOrFail($pagoId);
            if ($pago->eliminado || $pago->reembolso) {
                $this->invalid('pago', 'El pago ya fue corregido o reembolsado.');
            }
            $pago->update([$flag => true]);

            return $pago;
        }, 3);
    }

    public function cerrar(User $actor, int $corteId, mixed $efectivoReal, mixed $tarjetaReal, mixed $transferenciaReal): Corte
    {
        DomainAccess::requireRole($actor, ['cajero']);
        $cash = Money::parse($efectivoReal, 'total_caja_final');
        $card = Money::parse($tarjetaReal, 'total_tarjeta_real');
        $transfer = Money::parse($transferenciaReal, 'total_transferencia_real');

        return DB::transaction(function () use ($actor, $corteId, $cash, $card, $transfer) {
            $corte = $this->openOwned($actor, $corteId);
            $totals = $this->totales($corte);
            $real = Money::add(Money::subtract($cash, $corte->total_caja_inicial), Money::add($card, $transfer));
            $corte->update([...$totals, 'total_caja_final' => $cash, 'total_tarjeta_real' => $card,
                'total_transferencia_real' => $transfer, 'total_real' => $real, 'estado' => 'cerrado', 'fecha_cierre' => now()]);

            return $corte;
        }, 3);
    }

    public function resumen(User $actor, int $corteId): array
    {
        DomainAccess::requireRole($actor, ['cajero', 'administrador']);
        $corte = Corte::findOrFail($corteId);
        if ($actor->fresh()->rol !== 'administrador' && $corte->id_usuario !== $actor->id) {
            throw new AuthorizationException;
        }
        $totals = $corte->estado === 'abierto' ? $this->totales($corte) : $corte->only([
            'total_efectivo', 'total_tarjeta', 'total_transferencia', 'total_propinas', 'total_sistema',
        ]);

        return [...$totals, 'diferencia_efectivo' => $corte->estado === 'cerrado'
            ? Money::subtract(Money::add($corte->total_caja_inicial, $corte->total_efectivo), $corte->total_caja_final) : null,
            'diferencia_tarjeta' => $corte->estado === 'cerrado' ? Money::subtract($corte->total_tarjeta, $corte->total_tarjeta_real) : null,
            'diferencia_transferencia' => $corte->estado === 'cerrado' ? Money::subtract($corte->total_transferencia, $corte->total_transferencia_real) : null];
    }

    private function totales(Corte $corte): array
    {
        // Una sola consulta: snapshot consistente también al consultar un corte abierto.
        $result = $corte->pagos()->activos()->where('reembolso', false)->selectRaw("COALESCE(SUM(monto) FILTER (WHERE metodo_pago = 'efectivo'), 0) AS total_efectivo,
            COALESCE(SUM(monto) FILTER (WHERE metodo_pago = 'tarjeta'), 0) AS total_tarjeta,
            COALESCE(SUM(monto) FILTER (WHERE metodo_pago = 'transferencia'), 0) AS total_transferencia,
            COALESCE(SUM(propina), 0) AS total_propinas, COALESCE(SUM(monto), 0) AS total_sistema")->first();
        $totals = [];
        foreach (['total_efectivo', 'total_tarjeta', 'total_transferencia', 'total_propinas', 'total_sistema'] as $field) {
            $totals[$field] = Money::parse((string) $result->$field, $field);
        }

        return $totals;
    }

    private function openOwned(User $actor, int $id): Corte
    {
        $corte = Corte::lockForUpdate()->findOrFail($id);
        if ($corte->id_usuario !== $actor->id) {
            throw new AuthorizationException('El corte pertenece a otro cajero.');
        }
        if ($corte->estado !== 'abierto' || $corte->eliminado) {
            $this->invalid('corte', 'El corte ya está cerrado.');
        }

        return $corte;
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
