<?php

namespace App\Http\Middleware;

use App\Support\DomainAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Vistas del personal (dashboard, comandas): las abren los cuatro roles del personal, uno por uno.
// Clientes y visitantes no entran (el visitante ya lo detiene `auth`). Única excepción: los invitados de la
// demostración de clase, que son clientes con `demo_room` y ven el tablero de su sala; `ActiveTrackEatUser`
// ya exige que la demostración esté activada y vigente, y `/api/tablero` los limita a su sala.
class RequirePersonal
{
    public const PERSONAL = ['administrador', 'cocinero', 'mesero', 'cajero'];

    public function handle(Request $request, Closure $next): Response
    {
        $roles = self::PERSONAL;
        if ($request->user()?->demo_room) {
            $roles[] = 'cliente';
        }
        DomainAccess::requireRole($request->user(), $roles);

        return $next($request);
    }
}
