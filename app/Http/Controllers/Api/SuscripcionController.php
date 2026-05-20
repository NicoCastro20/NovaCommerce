<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SuscripcionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuscripcionController extends Controller
{
    /**
     * Devuelve el estado de la suscripción del usuario autenticado.
     */
    public function estado(Request $request): JsonResponse
    {
        $usuario = $request->user();

        return response()->json([
            'success' => true,
            'message' => 'Estado de la suscripción obtenido correctamente.',
            'data'    => [
                'suscripcion' => new SuscripcionResource($usuario),
            ],
        ]);
    }

    /**
     * Activa la suscripción Premium del usuario autenticado. El pago se da por
     * realizado: este endpoint simula la integración con la pasarela.
     */
    public function activar(Request $request): JsonResponse
    {
        $usuario = $request->user();

        if ($usuario->is_premium) {
            return response()->json([
                'success' => false,
                'message' => 'Ya tienes una suscripción Premium activa.',
            ], 422);
        }

        $usuario->activarPremium();

        return response()->json([
            'success' => true,
            'message' => '¡Bienvenido a NovaCommerce Premium! Disfruta de envíos gratuitos durante 1 año.',
            'data'    => [
                'suscripcion' => new SuscripcionResource($usuario->fresh()),
            ],
        ]);
    }

    /**
     * Cancela la renovación de la suscripción. Los beneficios se conservan
     * hasta la fecha de expiración.
     */
    public function cancelar(Request $request): JsonResponse
    {
        $usuario = $request->user();

        if (! $usuario->is_premium) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes una suscripción Premium activa que cancelar.',
            ], 422);
        }

        $usuario->cancelarPremium();
        $usuario->refresh();

        $resource = new SuscripcionResource($usuario);
        $fecha    = $resource->toArray($request)['premium_until_legible'] ?? 'la fecha de expiración';

        return response()->json([
            'success' => true,
            'message' => "Suscripción cancelada. Seguirás disfrutando de los beneficios hasta el {$fecha}.",
            'data'    => [
                'suscripcion' => $resource,
            ],
        ]);
    }
}
