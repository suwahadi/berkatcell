<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Alias middleware peran: 'admin' menjaga seluruh grup /admin.
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);

        // Percayai header proxy (ngrok/load balancer) agar Laravel mendeteksi HTTPS
        // dari X-Forwarded-Proto dan menghasilkan URL aset https:// (hindari mixed content).
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        // Webhook Midtrans adalah POST server-to-server tanpa token CSRF.
        // Dikecualikan di sini (cara Laravel 11+) agar tahan terhadap penggantian
        // nama kelas middleware CSRF (VerifyCsrfToken -> ValidateCsrfToken).
        $middleware->validateCsrfTokens(except: [
            'payments/midtrans/notification',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
