<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TrackEatController extends Controller
{
    public function sesion(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'user' => $user ? ['id' => $user->id, 'name' => $user->name, 'rol' => $user->rol,
                'demo_room' => $user->demo_room, 'demo_expires_at' => $user->demo_expires_at?->toISOString()] : null,
            'csrf' => csrf_token(),
            'socket' => ['key' => config('reverb.apps.apps.0.key'), 'url' => config('trackeat.socket_url')],
        ])->header('Cache-Control', 'no-store');
    }
}
