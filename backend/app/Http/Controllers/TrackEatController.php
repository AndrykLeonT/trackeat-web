<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TrackEatController extends Controller
{
    public function sesion(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'user' => $user ? ['id' => $user->id, 'name' => $user->name, 'rol' => $user->rol, 'estacion' => $user->estacion,
                'demo_room' => $user->demo_room, 'demo_expires_at' => $user->demo_expires_at?->toISOString()] : null,
            'csrf' => csrf_token(),
            // Límites del pedido para que el menú avise antes de enviar; el servidor los aplica de todos modos.
            'limites' => $user ? [
                'mesas_max' => config('trackeat.pedidos.mesas_max'),
                'cantidad_max' => config('trackeat.pedidos.cantidad_max'),
                'total_max' => config('trackeat.pedidos.'.($user->rol === 'cliente' ? 'total_max_cliente' : 'total_max_personal')),
            ] : null,
            // HU-05: la misma lista que valida el servidor; solo la necesita el personal que puede cancelar.
            'motivos_cancelacion' => $user && in_array($user->rol, ['mesero', 'cajero', 'administrador'], true)
                ? config('trackeat.pedidos.motivos_cancelacion') : [],
            'socket' => ['key' => config('reverb.apps.apps.0.key'), 'url' => config('trackeat.socket_url')],
        ])->header('Cache-Control', 'no-store');
    }
}
