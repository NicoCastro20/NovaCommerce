<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderCancelController extends Controller
{
    /**
     * Cancela un pedido del usuario autenticado antes del envío.
     * Devuelve el stock de los items.
     */
    public function cancel(Request $request, string $orderNumber): JsonResponse
    {
        $pedido = Order::query()
            ->where('user_id', $request->user()->id)
            ->where('order_number', $orderNumber)
            ->with(['items.producto'])
            ->first();

        if ($pedido === null) {
            return response()->json([
                'success' => false,
                'message' => 'Pedido no encontrado.',
            ], 404);
        }

        if (! $pedido->puedeCancelarse()) {
            $mensaje = $pedido->status === 'enviado'
                ? 'Este pedido no se puede cancelar porque ya ha sido enviado.'
                : 'Este pedido ya no se puede cancelar.';

            return response()->json([
                'success' => false,
                'message' => $mensaje,
            ], 422);
        }

        $pedido->cancelar();
        $pedido->refresh()->load(['items.producto', 'comprador', 'devolucion']);

        return response()->json([
            'success' => true,
            'message' => 'Pedido cancelado correctamente. Se ha devuelto el stock.',
            'data'    => [
                'order' => new OrderResource($pedido),
            ],
        ]);
    }
}
