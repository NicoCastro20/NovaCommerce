<?php

namespace App\Http\Controllers\Api\Empresa;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateReturnRequest;
use App\Http\Resources\ReturnResource;
use App\Models\ReturnRequest as ReturnModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EmpresaReturnController extends Controller
{
    /**
     * Devoluciones cuyos pedidos contienen al menos un producto de la empresa
     * autenticada.
     *
     * Filtros (query string):
     * - status: solicitada|aprobada|rechazada
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = (int) $request->input('per_page', 12);
        $perPage = max(1, min(48, $perPage));

        $empresaId = $request->user()->id;

        $consulta = ReturnModel::query()
            ->whereHas('pedido.items', fn ($q) => $q->where('seller_id', $empresaId))
            ->with([
                'cliente:id,name,email',
                'pedido' => fn ($q) => $q->with([
                    'items' => fn ($qi) => $qi->where('seller_id', $empresaId)
                        ->with('producto:id,name,slug'),
                ]),
            ]);

        if ($estado = $request->input('status')) {
            $consulta->where('status', $estado);
        }

        $devoluciones = $consulta->orderByDesc('created_at')->paginate($perPage);

        return ReturnResource::collection($devoluciones)->additional([
            'success' => true,
            'message' => 'Devoluciones obtenidas correctamente.',
        ]);
    }

    /**
     * Aprueba o rechaza una devolución, solo si contiene productos de la
     * empresa autenticada.
     */
    public function update(UpdateReturnRequest $request, int $id): JsonResponse
    {
        $empresaId = $request->user()->id;

        $devolucion = ReturnModel::query()
            ->where('id', $id)
            ->whereHas('pedido.items', fn ($q) => $q->where('seller_id', $empresaId))
            ->with(['pedido.items.producto'])
            ->first();

        if ($devolucion === null) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para modificar esta devolución.',
            ], 403);
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
