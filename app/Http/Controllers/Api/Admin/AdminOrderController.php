<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminOrderController extends Controller
{
    /**
     * Listado paginado de TODOS los pedidos con filtros.
     *
     * Filtros soportados (query string):
     * - status:    pendiente|pagado|enviado|entregado|cancelado
     * - user_id:   id del comprador
     * - date_from: YYYY-MM-DD (fecha mínima del pedido)
     * - date_to:   YYYY-MM-DD (fecha máxima del pedido)
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = (int) $request->input('per_page', 12);
        $perPage = max(1, min(48, $perPage));

        $consulta = Order::query()
            ->with(['comprador:id,name,email', 'items.producto:id,name,slug']);

        if ($estado = $request->input('status')) {
            $consulta->where('status', $estado);
        }

        if ($idUsuario = $request->input('user_id')) {
            $consulta->where('user_id', (int) $idUsuario);
        }

        if ($desde = $request->input('date_from')) {
            $consulta->whereDate('created_at', '>=', $desde);
        }

        if ($hasta = $request->input('date_to')) {
            $consulta->whereDate('created_at', '<=', $hasta);
        }

        $pedidos = $consulta->orderByDesc('created_at')->paginate($perPage);

        return OrderResource::collection($pedidos)->additional([
            'success' => true,
            'message' => 'Pedidos obtenidos correctamente.',
        ]);
    }

    /**
     * Cambia el estado de cualquier pedido.
     */
    public function updateStatus(UpdateOrderStatusRequest $request, int $id): JsonResponse
    {
        $pedido = Order::find($id);

        if ($pedido === null) {
            return response()->json([
                'success' => false,
                'message' => 'Pedido no encontrado.',
            ], 404);
        }

        $pedido->status = $request->validated('status');
        $pedido->save();

        return response()->json([
            'success' => true,
            'message' => 'Estado del pedido actualizado correctamente.',
            'data'    => [
                'id'     => $pedido->id,
                'status' => $pedido->status,
            ],
        ]);
    }
}
