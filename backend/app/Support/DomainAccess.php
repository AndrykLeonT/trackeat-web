<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class DomainAccess
{
    public static function requireRole(User $actor, array $roles): void
    {
        // Reconsultar: no confiar en roles o bajas de una instancia desactualizada.
        $current = $actor->exists ? User::find($actor->id) : null;
        if (! $current || $current->eliminado || ! in_array($current->rol, $roles, true)) {
            throw new AuthorizationException('No tiene permiso para esta operación.');
        }
    }
}
