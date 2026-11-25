<?php

namespace App\Policies;

use App\Models\Pedido;
use App\Models\User;
use Illuminate\Auth\Access\Response;

// Módulo 2 · Comandas (T-06, superficie A01). Reglas por objeto para avanzar la comanda de una estación.
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

        // Transición por rol: el cocinero prepara y marca listo; mesero y cajero solo entregan; el administrador todo.
        $permitida = match ($actor->rol) {
            'administrador' => true,
            'cocinero' => $estado !== 'entregado',
            'mesero', 'cajero' => $estado === 'entregado',
            default => false,
        };
        if (! $permitida) {
            return Response::deny('El rol no puede realizar esta transición.');
        }

        // Estación del cocinero: `general` atiende todas (incluidas las comandas `general` de pedidos anteriores);
        // `cocina` y `barra` solo la suya.
        if ($actor->rol === 'cocinero' && $actor->estacion !== 'general' && $actor->estacion !== $estacion) {
            return Response::deny("Esta comanda es de la estación {$estacion}.");
        }

        return Response::allow();
    }
}
