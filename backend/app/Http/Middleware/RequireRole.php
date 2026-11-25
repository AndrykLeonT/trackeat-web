<?php

namespace App\Http\Middleware;

use App\Support\DomainAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        DomainAccess::requireRole($request->user(), $roles);

        return $next($request);
    }
}
