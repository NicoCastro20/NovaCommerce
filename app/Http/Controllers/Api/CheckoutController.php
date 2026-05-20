<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    public const COSTE_ENVIO = 5.00;
    public const ENVIO_GRATIS_DESDE = 50.00;

    /**
     * Crea un pedido a partir del carrito del usuario en una transacción atómica.
     */
    public function store(CheckoutRequest $request): JsonResponse
    {
        $usuario = $request->user();
        $datos   = $request->validated();

        $carrito = Cart::with(['items.producto'])
            ->where('user_id', $usuario->id)
            ->first();

        if ($carrito === null || $carrito->items->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'El carrito está vacío.',
            ], 422);
        }

        // Validación previa: stock y disponibilidad de TODOS los items.
        foreach ($carrito->items as $item) {
            $producto = $item->producto;

            if ($producto === null || ! $producto->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => "El producto '{$item->product_name}' ya no está disponible.",
                ], 422);
            }

            if ($item->quantity > $producto->stock) {
                return response()->json([
                    'success' => false,
                    'message' => "No hay stock suficiente de '{$producto->name}'. Disponibles: {$producto->stock}.",
                ], 422);
            }
        }

        // Transacción: crear pedido, items, descontar stock y vaciar carrito.
        $pedido = DB::transaction(function () use ($carrito, $usuario, $datos) {
            $subtotal = 0.0;

            foreach ($carrito->items as $item) {
                $subtotal += (float) $item->producto->price * $item->quantity;
            }

            // El envío es gratuito si el usuario es Premium (siempre) o si el
            // subtotal supera el umbral configurado. En el resto de casos se
            // cobra la tarifa plana.
            $esPremium = $usuario->esPremium();
            $envio = match (true) {
                $esPremium                                     => 0.0,
                $subtotal >= self::ENVIO_GRATIS_DESDE          => 0.0,
                default                                        => self::COSTE_ENVIO,
            };
            $total = round($subtotal + $envio, 2);

            $pedido = Order::create([
                'user_id'              => $usuario->id,
                'status'               => 'pendiente',
                'subtotal'             => round($subtotal, 2),
                'shipping_cost'        => round($envio, 2),
                'envio_premium'        => $esPremium,
                'total'                => $total,
                'shipping_address'     => $datos['shipping_address'],
                'shipping_city'        => $datos['shipping_city'],
                'shipping_postal_code' => $datos['shipping_postal_code'],
                'shipping_country'     => $datos['shipping_country'] ?? 'España',
                'payment_method'       => $datos['payment_method']   ?? null,
                'notes'                => $datos['notes']            ?? null,
            ]);

            foreach ($carrito->items as $item) {
                $producto = $item->producto;

                OrderItem::create([
                    'order_id'      => $pedido->id,
                    'product_id'    => $producto->id,
                    'seller_id'     => $producto->user_id,
                    'product_name'  => $producto->name,
                    'product_price' => $producto->price,
                    'quantity'      => $item->quantity,
                    'subtotal'      => round((float) $producto->price * $item->quantity, 2),
                ]);

                // Descuento de stock con bloqueo optimista a nivel de columna.
                Product::where('id', $producto->id)
                    ->decrement('stock', $item->quantity);
            }

            // Vaciar el carrito tras el checkout.
            $carrito->items()->delete();

            return $pedido;
        });

        $pedido->load(['items.producto', 'comprador']);

        return response()->json([
            'success' => true,
            'message' => 'Pedido creado correctamente.',
            'data'    => [
                'order' => new OrderResource($pedido),
            ],
        ], 201);
    }
}
