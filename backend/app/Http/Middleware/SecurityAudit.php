<?php

namespace App\Http\Middleware;

use App\Support\SecurityLogRetention;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SecurityAudit
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('security_request_id', (string) Str::uuid());

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $route = $request->route();
        if (! $route || ! preg_match('~^(api/(pedidos|catalogo|tablero)|admin($|/(productos|categorias|purga|usuarios))|perfil($|/)|pedidos/|caja($|/)|login$|register$|logout$|forgot-password$|reset-password|broadcasting/auth$)~', $route->uri())) {
            return;
        }
        $status = $response->getStatusCode();
        $invalidForm = $request->hasSession() && $request->session()->has('errors');
        $unauthenticatedRedirect = $status === 302
            && $request->user() === null
            && preg_match('~^(api/(pedidos|tablero)|admin($|/(productos|categorias|purga|usuarios))|perfil($|/)|pedidos/|caja($|/)|broadcasting/auth$)~', $route->uri()) === 1;
        if ($request->isMethod('GET') && $status < 400 && ! $unauthenticatedRedirect) {
            return;
        }
        $role = $request->user()?->rol;
        SecurityLogRetention::prune();
        // Allowlist only. No user IDs, paths with IDs, exception strings or request input.
        $inertiaLocation = $status === 409 && $response->headers->has('X-Inertia-Location');
        $rejected = ($status >= 400 && ! $inertiaLocation) || $invalidForm || $unauthenticatedRedirect;
        Log::channel('security')->info('security_event', [
            'event' => $rejected ? 'request_rejected' : 'operation_completed',
            'role' => in_array($role, ['cliente', 'administrador', 'cocinero', 'mesero', 'cajero'], true) ? $role : 'visitante',
            'result' => $rejected ? 'rejected' : 'accepted',
            'reason' => $unauthenticatedRedirect ? 'authentication_required' : ($invalidForm ? 'invalid_input' : match ($status) {
                401 => 'authentication_required', 403 => 'role_denied', 404 => 'not_accessible', 409 => $inertiaLocation ? 'completed' : 'catalog_changed', 419 => 'csrf_rejected', 422 => 'invalid_input', 429 => 'rate_limit', default => $status >= 500 ? 'server_error' : ($status >= 400 ? 'request_rejected' : 'completed')
            }),
            'route' => $route->uri(), 'method' => $request->method(), 'status' => $status,
            'request_id' => $request->attributes->get('security_request_id') ?? (string) Str::uuid(),
        ]);
    }
}
