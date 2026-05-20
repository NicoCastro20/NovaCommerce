<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreReviewRequest;
use App\Http\Requests\Api\UpdateReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReviewController extends Controller
{
    /**
     * Listado público de reseñas de un producto (paginado).
     */
    public function index(Request $request, string $slug): AnonymousResourceCollection|JsonResponse
    {
        $producto = Product::where('slug', $slug)->first();

        if ($producto === null) {
            return response()->json([
                'success' => false,
                'message' => 'Producto no encontrado.',
            ], 404);
        }

        $perPage = (int) $request->input('per_page', 12);
        $perPage = max(1, min(48, $perPage));

        $resenas = Review::query()
            ->where('product_id', $producto->id)
            ->with('usuario:id,name')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return ReviewResource::collection($resenas)->additional([
            'success' => true,
            'message' => 'Reseñas obtenidas correctamente.',
            'meta_extra' => [
                'rating_average' => round((float) Review::where('product_id', $producto->id)->avg('rating'), 2),
                'reviews_count'  => Review::where('product_id', $producto->id)->count(),
            ],
        ]);
    }

    /**
     * Crea una reseña. Solo si el usuario tiene un pedido entregado con el producto
     * y aún no ha reseñado ese producto.
     */
    public function store(StoreReviewRequest $request, string $slug): JsonResponse
    {
        $producto = Product::where('slug', $slug)->first();

        if ($producto === null) {
            return response()->json([
                'success' => false,
                'message' => 'Producto no encontrado.',
            ], 404);
        }

        $usuario = $request->user();

        $tienePedidoEntregado = Order::query()
            ->where('user_id', $usuario->id)
            ->where('status', 'entregado')
            ->whereHas('items', fn ($q) => $q->where('product_id', $producto->id))
            ->exists();

        if (! $tienePedidoEntregado) {
            return response()->json([
                'success' => false,
                'message' => 'Solo puedes reseñar productos que hayas comprado y recibido.',
            ], 403);
        }

        $yaResenado = Review::where('user_id', $usuario->id)
            ->where('product_id', $producto->id)
            ->exists();

        if ($yaResenado) {
            return response()->json([
                'success' => false,
                'message' => 'Ya has dejado una reseña para este producto.',
            ], 422);
        }

        $resena = Review::create([
            'user_id'    => $usuario->id,
            'product_id' => $producto->id,
            'rating'     => (int) $request->validated('rating'),
            'comment'    => $request->validated('comment'),
        ]);

        $resena->load('usuario:id,name');

        return response()->json([
            'success' => true,
            'message' => 'Reseña creada correctamente.',
            'data'    => [
                'review' => new ReviewResource($resena),
            ],
        ], 201);
    }

    /**
     * Actualiza una reseña propia.
     */
    public function update(UpdateReviewRequest $request, int $id): JsonResponse
    {
        $resena = Review::find($id);

        if ($resena === null) {
            return response()->json([
                'success' => false,
                'message' => 'Reseña no encontrada.',
            ], 404);
        }

        if ((int) $resena->user_id !== (int) $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para modificar esta reseña.',
            ], 403);
        }

        $resena->update($request->validated());
        $resena->load('usuario:id,name');

        return response()->json([
            'success' => true,
            'message' => 'Reseña actualizada correctamente.',
            'data'    => [
                'review' => new ReviewResource($resena),
            ],
        ]);
    }

    /**
     * Elimina una reseña propia.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $resena = Review::find($id);

        if ($resena === null) {
            return response()->json([
                'success' => false,
                'message' => 'Reseña no encontrada.',
            ], 404);
        }

        if ((int) $resena->user_id !== (int) $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para eliminar esta reseña.',
            ], 403);
        }

        $resena->delete();

        return response()->json([
            'success' => true,
            'message' => 'Reseña eliminada correctamente.',
        ]);
    }
}
