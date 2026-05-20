<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CartResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $items = $this->items->map(function ($item) {
            $producto      = $item->producto;
            $imagen        = $producto?->imagenes?->first();
            $rutaImagen    = $imagen?->image_path;
            $imagenUrl     = $this->urlImagen($rutaImagen);
            $precioUnitario = $producto !== null ? (float) $producto->price : 0.0;

            return [
                'id'             => $item->id,
                'product_id'     => $item->product_id,
                'quantity'       => $item->quantity,
                'unit_price'     => $precioUnitario,
                'subtotal'       => round($precioUnitario * $item->quantity, 2),
                'product'        => $producto !== null ? [
                    'id'            => $producto->id,
                    'name'          => $producto->name,
                    'slug'          => $producto->slug,
                    'price'         => (float) $producto->price,
                    'stock'         => $producto->stock,
                    'is_active'     => (bool) $producto->is_active,
                    'primary_image' => $imagenUrl,
                    'seller_id'     => $producto->user_id,
                ] : null,
            ];
        });

        $total = round((float) $items->sum('subtotal'), 2);

        return [
            'id'           => $this->id,
            'user_id'      => $this->user_id,
            'items'        => $items,
            'items_count'  => $items->count(),
            'total_units'  => (int) $items->sum('quantity'),
            'total'        => $total,
        ];
    }

    private function urlImagen(?string $ruta): ?string
    {
        if ($ruta === null || $ruta === '') {
            return null;
        }

        if (str_starts_with($ruta, 'http://') || str_starts_with($ruta, 'https://')) {
            return $ruta;
        }

        return Storage::disk('public')->url($ruta);
    }
}
