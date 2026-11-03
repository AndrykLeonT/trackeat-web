<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Detrás del balanceador permite comprobar qué nodo atendió cada petición.
class IdentificarNodo
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Servidor', (string) config('trackeat.nodo'));

        return $response;
    }
}
