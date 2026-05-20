<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AddCartItemRequest;
use App\Http\Requests\Api\UpdateCartItemRequest;
use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    /**
     * Devuelve el carrito del usuario autenticado.
     */
    public function show(Request $request): JsonResponse
    {
        $carrito = $this->carritoDelUsuario($request);

        return response()->json([
            'success' => true,
            'message' => 'Carrito obtenido correctamente.',
            'data'    => [
                'cart' => new CartResource($carrito),
            ],
        ]);
    }

    /**
     * Añade un producto al carrito (suma cantidad si ya existe).
     */
    public function addItem(AddCartItemRequest $request): JsonResponse
    {
        $datos    = $request->validated();
        $producto = Product::find($datos['product_id']);

        if ($producto === null || ! $producto->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Producto no disponible.',
            ], 404);
        }

        if ((int) $producto->user_id === (int) $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'No puedes comprar tu propio producto.',
            ], 422);
        }

        $carrito = $this->carritoDelUsuario($request);

        $item = $carrito->items()->where('product_id', $producto->id)->first();
        $cantidadFinal = ($item?->quantity ?? 0) + (int) $datos['quantity'];

        if ($cantidadFinal > $producto->stock) {
            return response()->json([
                'success' => false,
                'message' => 'No hay stock suficiente. Disponibles: ' . $producto->stock . '.',
            ], 422);
        }

        if ($item === null) {
            $carrito->items()->create([
                'product_id' => $producto->id,
                'quantity'   => $cantidadFinal,
            ]);
        } else {
            $item->update(['quantity' => $cantidadFinal]);
        }

        $carrito->load(['items.producto.imagenes']);

        return response()->json([
            'success' => true,
            'message' => 'Producto añadido al carrito correctamente.',
            'data'    => [
                'cart' => new CartResource($carrito),
            ],
        ], 201);
    }

    /**
     * Actualiza la cantidad de un item del carrito.
     */
    public function updateItem(UpdateCartItemRequest $request, int $id): JsonResponse
    {
        $carrito = $this->carritoDelUsuario($request);

        $item = $carrito->items()->where('id', $id)->first();

        if ($item === null) {
            return response()->json([
                'success' => false,
                'message' => 'Item del carrito no encontrado.',
            ], 404);
        }

        $producto = $item->producto;
        $cantidad = (int) $request->validated('quantity');

        if ($producto === null || ! $producto->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Producto no disponible.',
            ], 422);
        }

        if ($cantidad > $producto->stock) {
            return response()->json([
                'success' => false,
                'message' => 'No hay stock suficiente. Disponibles: ' . $producto->stock . '.',
            ], 422);
        }

        $item->update(['quantity' => $cantidad]);
        $carrito->load(['items.producto.imagenes']);

        return response()->json([
            'success' => true,
            'message' => 'Cantidad actualizada correctamente.',
            'data'    => [
                'cart' => new CartResource($carrito),
            ],
        ]);
    }

    /**
     * Elimina un item del carrito.
     */
    public function removeItem(Request $request, int $id): JsonResponse
    {
        $carrito = $this->carritoDelUsuario($request);

        $item = $carrito->items()->where('id', $id)->first();

        if ($item === null) {
            return response()->json([
                'success' => false,
                'message' => 'Item del carrito no encontrado.',
            ], 404);
        }

        $item->delete();
        $carrito->load(['items.producto.imagenes']);

        return response()->json([
            'success' => true,
            'message' => 'Producto eliminado del carrito correctamente.',
            'data'    => [
                'cart' => new CartResource($carrito),
            ],
        ]);
    }

    /**
     * Vacía completamente el carrito del usuario.
     */
    public function clear(Request $request): JsonResponse
    {
        $carrito = $this->carritoDelUsuario($request);
        $carrito->items()->delete();
        $carrito->load(['items.producto.imagenes']);

        return response()->json([
            'success' => true,
            'message' => 'Carrito vaciado correctamente.',
            'data'    => [
                'cart' => new CartResource($carrito),
            ],
        ]);
    }

    /**
     * Devuelve el carrito del usuario autenticado, creándolo si no existe,
     * con sus items y productos cargados.
     */
    private function carritoDelUsuario(Request $request): Cart
    {
        $carrito = Cart::firstOrCreate(['user_id' => $request->user()->id]);
        $carrito->load(['items.producto.imagenes']);

        return $carrito;
    }
}
