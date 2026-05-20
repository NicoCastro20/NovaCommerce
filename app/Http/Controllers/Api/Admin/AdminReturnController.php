<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateReturnRequest;
use App\Http\Resources\ReturnResource;
use App\Models\ReturnRequest as ReturnModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminReturnController extends Controller
{
    /**
     * Listado paginado de TODAS las devoluciones con filtros.
     *
     * Filtros (query string):
     * - status:    solicitada|aprobada|rechazada
     * - date_from: YYYY-MM-DD
     * - date_to:   YYYY-MM-DD
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = (int) $request->input('per_page', 12);
        $perPage = max(1, min(48, $perPage));

        $consulta = ReturnModel::query()
            ->with(['pedido.items.producto:id,name,slug', 'cliente:id,name,email']);

        if ($estado = $request->input('status')) {
            $consulta->where('status', $estado);
        }

        if ($desde = $request->input('date_from')) {
            $consulta->whereDate('created_at', '>=', $desde);
        }

        if ($hasta = $request->input('date_to')) {
            $consulta->whereDate('created_at', '<=', $hasta);
        }

        $devoluciones = $consulta->orderByDesc('created_at')->paginate($perPage);

        return ReturnResource::collection($devoluciones)->additional([
            'success' => true,
            'message' => 'Devoluciones obtenidas correctamente.',
        ]);
    }

    /**
     * Aprueba o rechaza una devolución.
     */
    public function update(UpdateReturnRequest $request, int $id): JsonResponse
    {
        $devolucion = ReturnModel::with(['pedido.items.producto'])->find($id);

        if ($devolucion === null) {
            return response()->json([
                'success' => false,
                'message' => 'Devolución no encontrada.',
            ], 404);
        }

        if ($devolucion->status !== 'solicitada') {
            return response()->json([
                'success' => false,
                'message' => 'Esta devolución ya ha sido resuelta.',
            ], 422);
        }

        $datos  = $request->validated();
        $accion = $datos['action'];
        $notas  = $datos['admin_notes'] ?? null;

        if ($accion === 'aprobar') {
            $devolucion->pedido->aprobarDevolucion($notas);
            $devolucion->refresh()->load(['pedido.items', 'cliente']);

            return response()->json([
                'success' => true,
                'message' => 'Devolución aprobada. Stock devuelto.',
                'data'    => [
                    'return' => new ReturnResource($devolucion),
                ],
            ]);
        }

        $devolucion->status = 'rechazada';
        $devolucion->admin_notes = $notas;
        $devolucion->resolved_at = now();
        $devolucion->save();

        $devolucion->load(['pedido.items', 'cliente']);

        return response()->json([
            'success' => true,
            'message' => 'Devolución rechazada.',
            'data'    => [
                'return' => new ReturnResource($devolucion),
            ],
        ]);
    }
}
