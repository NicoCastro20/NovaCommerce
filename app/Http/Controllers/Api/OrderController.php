<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    /**
     * Listado paginado de pedidos del comprador autenticado.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = (int) $request->input('per_page', 12);
        $perPage = max(1, min(48, $perPage));

        $pedidos = Order::query()
            ->where('user_id', $request->user()->id)
            ->with(['items.producto', 'devolucion'])
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return OrderResource::collection($pedidos)->additional([
            'success' => true,
            'message' => 'Pedidos obtenidos correctamente.',
        ]);
    }

    /**
     * Detalle de un pedido del comprador autenticado por su `order_number`.
     */
    public function show(Request $request, string $orderNumber): JsonResponse
    {
        $pedido = Order::query()
            ->where('user_id', $request->user()->id)
            ->where('order_number', $orderNumber)
            ->with(['items.producto', 'comprador', 'devolucion'])
            ->first();

        if ($pedido === null) {
            return response()->json([
                'success' => false,
                'message' => 'Pedido no encontrado.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pedido obtenido correctamente.',
            'data'    => [
                'order' => new OrderResource($pedido),
            ],
        ]);
    }
}
