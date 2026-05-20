<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // La SPA autentica con Bearer tokens (Sanctum personal access tokens),
        // no con cookies de sesión. Por eso NO se antepone
        // EnsureFrontendRequestsAreStateful al stack de la API: ese middleware
        // exigiría cookie CSRF y rompería los POST de /register y /login.

        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
            'role'     => \App\Http\Middleware\CheckRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Respuestas JSON consistentes ({success, message, errors}) para la API.
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Los datos enviados no son válidos.',
                    'errors'  => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No has iniciado sesión.',
                ], 401);
            }
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $segundos = (int) ($e->getHeaders()['Retry-After'] ?? 60);

                return response()->json([
                    'success'     => false,
                    'message'     => 'Demasiados intentos. Inténtalo de nuevo en unos segundos.',
                    'retry_after' => $segundos,
                ], 429);
            }
        });
    })->create();
