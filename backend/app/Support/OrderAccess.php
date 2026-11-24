<?php

namespace App\Support;

use App\Models\Pedido;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class OrderAccess
{
    public static function query(User $actor): Builder
    {
        $query = Pedido::activos();
        if ($actor->demo_room) {
            return $query->where('id_usuario', $actor->id)->where('demo_room', $actor->demo_room);
        }
        if ($actor->rol === 'cliente') {
            return $query->where('id_usuario', $actor->id);
        }
        DomainAccess::requireRole($actor, ['administrador', 'cocinero', 'mesero', 'cajero']);

        return $query;
    }

    public static function find(User $actor, int $id): Pedido
    {
        return self::query($actor)->findOrFail($id);
    }
}
