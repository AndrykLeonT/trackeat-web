<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\IdentificarNodo;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        channels: __DIR__.'/../routes/channels.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware([])->group(__DIR__.'/../routes/salud.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        // TLS terminates at cloudflared; local servers listen only on loopback.
        // Behind the Nginx balancer on another PC, TRUSTED_PROXIES holds its IP.
        $middleware->trustProxies(at: array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('TRUSTED_PROXIES', '127.0.0.1,::1'))
        ))));
        $middleware->append(IdentificarNodo::class);
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
