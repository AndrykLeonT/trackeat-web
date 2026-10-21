<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ActiveTrackEatUser
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        abort_unless($user && ! $user->eliminado, 403, 'Cuenta no disponible.');
        abort_if($user->demo_room && (! config('trackeat.demo_enabled') || ! $user->demo_expires_at?->isFuture()), 403, 'La demostración terminó.');

        return $next($request);
    }
}
