<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductCollection;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminProductController extends Controller
{
    /**
     * Listado paginado de TODOS los productos (incluidos inactivos y eliminados).
     *
     * Filtros (query string):
     * - search:    coincidencia LIKE en `name`
     * - category:  slug de la categoría (incluye productos de subcategorías)
     * - seller_id: id del usuario que publicó el producto (empresa o particular)
     * - type:      'nuevo' | 'segunda_mano'
     * - status:    'active' | 'inactive' | 'trashed'
     */
    public function index(Request $request): ProductCollection
    {
        $perPage = (int) $request->input('per_page', 12);
        $perPage = max(1, min(48, $perPage));

        $consulta = Product::query()
            ->withTrashed()
            ->with(['categoria', 'imagenPrincipal', 'vendedor:id,name,role,company_name'])
            ->withCount('resenas as reviews_count')
            ->withAvg('resenas as rating_average', 'rating');

        if ($termino = $request->input('search')) {
            $consulta->where('name', 'like', "%{$termino}%");
        }

        if ($slugCategoria = $request->input('category')) {
            $consulta->whereHas('categoria', function ($q) use ($slugCategoria) {
                $q->where('slug', $slugCategoria)
                  ->orWhereHas('padre', fn ($qp) => $qp->where('slug', $slugCategoria));
            });
        }

        if ($idVendedor = $request->input('seller_id')) {
            $consulta->where('user_id', (int) $idVendedor);
        }

        if ($tipo = $request->input('type')) {
            if (in_array($tipo, ['nuevo', 'segunda_mano'], true)) {
                $consulta->where('type', $tipo);
            }
        }

        $estado = $request->input('status');
        if ($estado === 'active') {
            $consulta->where('is_active', true)->whereNull('deleted_at');
        } elseif ($estado === 'inactive') {
            $consulta->where('is_active', false)->whereNull('deleted_at');
        } elseif ($estado === 'trashed') {
            $consulta->whereNotNull('deleted_at');
        }

        $productos = $consulta->orderByDesc('created_at')->paginate($perPage);

        return (new ProductCollection($productos))->additional([
            'success' => true,
            'message' => 'Productos obtenidos correctamente.',
        ]);
    }

    /**
     * Activa o desactiva un producto invirtiendo el flag `is_active`.
     */
    public function toggle(int $id): JsonResponse
    {
        $producto = Product::withTrashed()->find($id);

        if ($producto === null) {
            return response()->json([
                'success' => false,
                'message' => 'Producto no encontrado.',
            ], 404);
        }

        $producto->is_active = ! $producto->is_active;
        $producto->save();

        return response()->json([
            'success' => true,
            'message' => $producto->is_active
                ? 'Producto activado correctamente.'
                : 'Producto desactivado correctamente.',
            'data'    => [
                'id'        => $producto->id,
                'is_active' => (bool) $producto->is_active,
            ],
        ]);
    }

    /**
     * Elimina (soft delete) un producto de cualquier vendedor.
     */
    public function destroy(int $id): JsonResponse
    {
        $producto = Product::find($id);

        if ($producto === null) {
            return response()->json([
                'success' => false,
                'message' => 'Producto no encontrado.',
            ], 404);
        }

        $producto->delete();

        return response()->json([
            'success' => true,
            'message' => 'Producto eliminado correctamente.',
        ]);
    }
}
