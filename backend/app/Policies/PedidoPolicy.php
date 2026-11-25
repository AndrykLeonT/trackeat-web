<?php

namespace App\Policies;

use App\Models\Pedido;
use App\Models\User;
use Illuminate\Auth\Access\Response;

// Módulo 2 · Comandas (T-06, superficie A01). Reglas por objeto para avanzar la comanda de una estación,
// entregar un pedido completo (HU-09) y cancelarlo (HU-05).
// La llama PedidoService::avanzar dentro de la transacción, con el pedido bloqueado y el usuario recién leído
// de la base, para que nadie se la salte llamando al servicio directo.
class PedidoPolicy
{
    public function avanzar(User $actor, Pedido $pedido, string $estacion, string $estado): Response
    {
        // Sala: un usuario de una sala de demostración solo toca pedidos de esa sala. El personal del restaurante
        // (sin sala) opera todas, porque es quien atiende los pedidos de los invitados en la presentación.
        if ($actor->demo_room !== null && $actor->demo_room !== $pedido->demo_room) {
            return Response::denyAsNotFound();
        }

        // HU-09: la entrega es todo o nada, a nivel de pedido (PedidoService::entregar). Nadie entrega UNA estación.
        if ($estado === 'entregado') {
            return Response::deny('La entrega es por pedido completo.');
        }
        // Por estación solo se prepara y se marca listo: cocinero y administrador. Mesero y cajero entregan pedidos.
        if (! in_array($actor->rol, ['cocinero', 'administrador'], true)) {
            return Response::deny('El rol no puede realizar esta transición.');
        }

        // Estación del cocinero: `general` atiende todas (incluidas las comandas `general` de pedidos anteriores);
        // `cocina` y `barra` solo la suya.
        if ($actor->rol === 'cocinero' && $actor->estacion !== 'general' && $actor->estacion !== $estacion) {
            return Response::deny("Esta comanda es de la estación {$estacion}.");
        }

        return Response::allow();
    }

    // HU-09. Quién entrega un pedido (completo): mesero, cajero y administrador. Que esté listo lo revisa el servicio.
    public function entregar(User $actor, Pedido $pedido): Response
    {
        if ($actor->demo_room !== null && $actor->demo_room !== $pedido->demo_room) {
            return Response::denyAsNotFound();
        }

        return in_array($actor->rol, ['mesero', 'cajero', 'administrador'], true)
            ? Response::allow()
            : Response::deny('Tu rol no puede entregar pedidos.');
    }

    // HU-05. Quién puede cancelar. Las reglas del pedido (entregado, pagos activos) las aplica el servicio.
    // Se evalúa con el pedido y sus estaciones bloqueados, así que el estado de las estaciones es el real.
    public function cancelar(User $actor, Pedido $pedido): Response
    {
        if ($actor->demo_room !== null && $actor->demo_room !== $pedido->demo_room) {
            return Response::denyAsNotFound();
        }
        if ($actor->rol === 'cliente') {
            if ($pedido->id_usuario !== $actor->id) {
                return Response::denyAsNotFound();
            }
            // El cliente solo cancela mientras nadie lo ha empezado a preparar.
            if ($pedido->estaciones()->where('estado', '!=', 'recibido')->exists()) {
                return Response::deny('Tu pedido ya se empezó a preparar. Pide ayuda al personal.');
            }

            return Response::allow();
        }

        return in_array($actor->rol, ['mesero', 'cajero', 'administrador'], true)
            ? Response::allow()
            : Response::deny('Tu rol no puede cancelar pedidos.');
    }
}
