<?php

namespace App\Http\Controllers\Api\Empresa;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReturnResource;
use App\Models\ReturnRequest as ReturnModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EmpresaReturnController extends Controller
{
    /**
     * Devoluciones (sólo lectura) cuyos pedidos contienen al menos un producto
     * de la empresa autenticada. Tras pasar a flujo automático, no hay acciones
     * de aprobación/rechazo: el panel es puramente informativo.
     *
     * Filtros (query string):
     * - status: aprobada (otros valores se ignoran)
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
}
