<?php

namespace App\Http\Controllers\Api\Empresa;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EmpresaOrderController extends Controller
{
    /**
     * Pedidos que contienen al menos un producto de la empresa autenticada.
     * La empresa solo ve los items que le pertenecen.
     *
     * Filtros (query string):
     * - status: pendiente|pagado|enviado|entregado|cancelado
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = (int) $request->input('per_page', 12);
        $perPage = max(1, min(48, $perPage));

        $empresaId = $request->user()->id;

        $consulta = Order::query()
            ->whereHas('items', fn ($q) => $q->where('seller_id', $empresaId))
            ->with([
                'comprador:id,name,email',
                'items' => fn ($q) => $q->where('seller_id', $empresaId)->with('producto:id,name,slug'),
            ]);

        if ($estado = $request->input('status')) {
            $consulta->where('status', $estado);
        }

        $pedidos = $consulta->orderByDesc('created_at')->paginate($perPage);

        return OrderResource::collection($pedidos)->additional([
            'success' => true,
            'message' => 'Pedidos obtenidos correctamente.',
        ]);
    }

    /**
     * Cambia el estado de un pedido. Solo permitido si contiene productos de la empresa.
     */
    public function updateStatus(UpdateOrderStatusRequest $request, int $id): JsonResponse
    {
        $empresaId = $request->user()->id;

        $pedido = Order::query()
            ->where('id', $id)
            ->whereHas('items', fn ($q) => $q->where('seller_id', $empresaId))
            ->first();

        if ($pedido === null) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para modificar este pedido.',
            ], 403);
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
