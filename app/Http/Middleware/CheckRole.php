<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Verifica que el usuario autenticado tenga uno de los roles permitidos.
     *
     * Uso en rutas: ->middleware('role:admin') o ->middleware('role:empresa,admin').
     */
    public function handle(Request $request, Closure $next, string ...$rolesPermitidos): Response
    {
        $usuario = $request->user();

        if ($usuario === null) {
            return response()->json([
                'success' => false,
                'message' => 'No has iniciado sesión.',
            ], 401);
        }

        if (! in_array($usuario->role, $rolesPermitidos, true)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para realizar esta acción.',
            ], 403);
        }

        return $next($request);
    }
}
