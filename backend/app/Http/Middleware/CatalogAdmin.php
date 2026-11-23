<?php

namespace App\Http\Middleware;

use App\Support\DomainAccess;
use Closure;
use Illuminate\Http\Request;

class CatalogAdmin
{
    public function handle(Request $request, Closure $next)
    {
        DomainAccess::requireRole($request->user(), ['administrador']);

        return $next($request);
    }
}
