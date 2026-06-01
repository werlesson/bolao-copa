<?php

use App\Http\Middleware\AuthenticateFromCookie;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\HandleCors as AppHandleCors;
use App\Http\Middleware\SetSecurityHeaders;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust the upstream reverse proxy (Traefik/Nginx) so that rate limiting
        // and IP detection use the real client IP from X-Forwarded-For.
        // In production, restrict this to the actual proxy IP range if possible.
        $middleware->trustProxies(at: '*');

        $middleware->replace(HandleCors::class, AppHandleCors::class);

        $middleware->encryptCookies(except: [
            'auth_token',
        ]);

        // Global middleware runs before the priority-sorted group+route pipeline,
        // so this always converts the auth_token cookie into a Bearer header
        // before Sanctum's auth:sanctum middleware checks for authentication.
        $middleware->append(AuthenticateFromCookie::class);
        $middleware->append(SetSecurityHeaders::class);

        $middleware->alias(['admin' => EnsureAdmin::class]);

        // API pura: nunca redirecionar guests para o SPA (quebra CORS no $fetch)
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Não autenticado.'], 401);
            }
        });
    })->create();
