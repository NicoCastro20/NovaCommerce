<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReturnResource;
use App\Models\ReturnRequest as ReturnModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminReturnController extends Controller
{
    /**
     * Listado paginado (sólo lectura) de TODAS las devoluciones con filtros.
     * Tras el flujo automático, la administración no resuelve manualmente.
     *
     * Filtros (query string):
     * - status:    aprobada (otros valores se ignoran)
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
}
