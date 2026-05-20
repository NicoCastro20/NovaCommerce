<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreReturnRequest;
use App\Http\Resources\ReturnResource;
use App\Models\Order;
use App\Models\ReturnRequest as ReturnModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReturnController extends Controller
{
    /**
     * Crea una solicitud de devolución sobre un pedido entregado.
     */
    public function store(StoreReturnRequest $request, string $orderNumber): JsonResponse
    {
        $pedido = Order::query()
            ->where('user_id', $request->user()->id)
            ->where('order_number', $orderNumber)
            ->with('devolucion')
            ->first();

        if ($pedido === null) {
            return response()->json([
                'success' => false,
                'message' => 'Pedido no encontrado.',
            ], 404);
        }

        if ($pedido->devolucion !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe una solicitud de devolución para este pedido.',
            ], 422);
        }

        if ($pedido->status !== 'entregado') {
            return response()->json([
                'success' => false,
                'message' => 'Este pedido no está en estado de entrega.',
            ], 422);
        }

        if (! $pedido->puedeDevolverse()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede solicitar la devolución. El plazo de 14 días ha expirado.',
            ], 422);
        }

        $datos = $request->validated();

        $devolucion = ReturnModel::create([
            'order_id'    => $pedido->id,
            'user_id'     => $request->user()->id,
            'reason'      => $datos['reason'],
            'description' => $datos['description'] ?? null,
            'status'      => 'solicitada',
        ]);

        $devolucion->load(['pedido.items', 'cliente']);

        return response()->json([
            'success' => true,
            'message' => 'Solicitud de devolución enviada. Te notificaremos cuando sea revisada.',
            'data'    => [
                'return' => new ReturnResource($devolucion),
            ],
        ], 201);
    }

    /**
     * Lista las devoluciones del usuario autenticado.
     */
    public function misDevoluciones(Request $request): AnonymousResourceCollection
    {
        $perPage = (int) $request->input('per_page', 12);
        $perPage = max(1, min(48, $perPage));

        $devoluciones = ReturnModel::query()
            ->where('user_id', $request->user()->id)
            ->with(['pedido.items'])
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return ReturnResource::collection($devoluciones)->additional([
            'success' => true,
            'message' => 'Devoluciones obtenidas correctamente.',
        ]);
    }
}
