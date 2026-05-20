<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WishlistResource;
use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WishlistController extends Controller
{
    /**
     * Listado paginado de la lista de deseos del usuario autenticado.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = (int) $request->input('per_page', 12);
        $perPage = max(1, min(48, $perPage));

        $entradas = Wishlist::query()
            ->where('user_id', $request->user()->id)
            ->with(['producto.imagenes'])
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return WishlistResource::collection($entradas)->additional([
            'success' => true,
            'message' => 'Lista de deseos obtenida correctamente.',
        ]);
    }

    /**
     * Toggle: si el producto ya está en la lista, lo elimina; si no, lo añade.
     */
    public function toggle(Request $request, int $productId): JsonResponse
    {
        $producto = Product::find($productId);

        if ($producto === null) {
            return response()->json([
                'success' => false,
                'message' => 'Producto no encontrado.',
            ], 404);
        }

        $usuarioId = $request->user()->id;

        $existente = Wishlist::where('user_id', $usuarioId)
            ->where('product_id', $producto->id)
            ->first();

        if ($existente !== null) {
            $existente->delete();

            return response()->json([
                'success' => true,
                'message' => 'Producto eliminado de tu lista de deseos.',
                'data'    => [
                    'in_wishlist' => false,
                ],
            ]);
        }

        Wishlist::create([
            'user_id'    => $usuarioId,
            'product_id' => $producto->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Producto añadido a tu lista de deseos.',
            'data'    => [
                'in_wishlist' => true,
            ],
        ], 201);
    }
}
