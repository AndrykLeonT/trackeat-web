<?php

namespace App\Services;

use App\Models\Corte;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\User;
use App\Support\DomainAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PurgaService
{
    public function pago(User $actor, int $pagoId, string $password): void
    {
        DomainAccess::requireRole($actor, ['administrador']);
        if (! Hash::check($password, $actor->fresh()->password)) {
            throw ValidationException::withMessages(['password' => 'Contraseña incorrecta.']);
        }
        DB::transaction(function () use ($pagoId) {
            $reference = Pago::findOrFail($pagoId);
            Pedido::lockForUpdate()->findOrFail($reference->id_pedido);
            $corte = Corte::lockForUpdate()->findOrFail($reference->id_corte);
            $pago = Pago::lockForUpdate()->findOrFail($pagoId);
            if (! $pago->eliminado || $corte->estado === 'cerrado') {
                throw ValidationException::withMessages(['pago' => 'Solo puede purgar capturas eliminadas de cortes abiertos.']);
            }
            // Pago es una hoja del grafo: no hay registros dependientes.
            $pago->delete();
        }, 3);
    }
}
