<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'slug'            => $this->slug,
            'description'     => $this->description,
            'price'               => (float) $this->price,
            'stock'               => $this->stock,
            'is_active'           => (bool) $this->is_active,
            'type'                => $this->type,
            'condition'           => $this->condition,
            'is_on_offer'         => (bool) $this->is_on_offer,
            'original_price'      => $this->is_on_offer ? (float) $this->original_price : null,
            'discount_percentage' => $this->discount_percentage,
            'offer_label'         => $this->is_on_offer ? $this->offer_label : null,
            'offer_starts_at'     => $this->is_on_offer ? $this->offer_starts_at : null,
            'offer_ends_at'       => $this->is_on_offer ? $this->offer_ends_at : null,
            'created_at'          => $this->created_at,
            'updated_at'          => $this->updated_at,
            'deleted_at'          => $this->whenNotNull($this->deleted_at),
            'category'        => $this->whenLoaded('categoria', fn () => [
                'id'   => $this->categoria->id,
                'name' => $this->categoria->name,
                'slug' => $this->categoria->slug,
                'icon' => $this->categoria->icon,
            ]),
            'seller'          => $this->whenLoaded('vendedor', fn () => [
                'id'           => $this->vendedor->id,
                'name'         => $this->vendedor->name,
                'role'         => $this->vendedor->role,
                'company_name' => $this->vendedor->company_name,
                'avatar'       => $this->vendedor->avatar,
            ]),
            'images'          => $this->whenLoaded('imagenes', fn () => $this->imagenes->map(fn ($img) => [
                'id'         => $img->id,
                'url'        => $this->urlImagen($img->image_path),
                'is_primary' => (bool) $img->is_primary,
                'sort_order' => $img->sort_order,
            ])),
            'primary_image'   => $this->when(
                $this->relationLoaded('imagenPrincipal'),
                fn () => optional($this->imagenPrincipal->first())->image_path
                    ? $this->urlImagen($this->imagenPrincipal->first()->image_path)
                    : null
            ),
            'reviews'         => $this->whenLoaded('resenas', fn () => $this->resenas->map(fn ($r) => [
                'id'         => $r->id,
                'rating'     => $r->rating,
                'comment'    => $r->comment,
                'created_at' => $r->created_at,
                'user'       => $r->relationLoaded('usuario') && $r->usuario ? [
                    'id'     => $r->usuario->id,
                    'name'   => $r->usuario->name,
                    'avatar' => $r->usuario->avatar,
                ] : null,
            ])),
            'reviews_count'   => $this->when(
                isset($this->reviews_count) || $this->relationLoaded('resenas'),
                fn () => $this->reviews_count ?? $this->resenas->count()
            ),
            'rating_average'  => $this->when(
                isset($this->rating_average) || $this->relationLoaded('resenas'),
                fn () => $this->rating_average !== null
                    ? round((float) $this->rating_average, 2)
                    : ($this->relationLoaded('resenas') && $this->resenas->count()
                        ? round((float) $this->resenas->avg('rating'), 2)
                        : null)
            ),
        ];
    }

    /**
     * Convierte una ruta relativa de imagen en URL absoluta usando el disco public.
     * Si ya viene como URL completa (placeholder, CDN), la devuelve sin tocar.
     */
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
