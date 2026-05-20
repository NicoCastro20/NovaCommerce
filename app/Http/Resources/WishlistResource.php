<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class WishlistResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $producto = $this->producto;
        $imagen   = $producto?->imagenes?->first();

        return [
            'id'         => $this->id,
            'product_id' => $this->product_id,
            'created_at' => $this->created_at,
            'product'    => $producto !== null ? [
                'id'            => $producto->id,
                'name'          => $producto->name,
                'slug'          => $producto->slug,
                'price'         => (float) $producto->price,
                'stock'         => $producto->stock,
                'is_active'     => (bool) $producto->is_active,
                'primary_image' => $this->urlImagen($imagen?->image_path),
            ] : null,
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
