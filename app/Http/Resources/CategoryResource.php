<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'name'      => $this->name,
            'slug'      => $this->slug,
            'icon'      => $this->icon,
            'parent_id' => $this->parent_id,
            'children'  => $this->whenLoaded(
                'subcategorias',
                fn () => CategoryResource::collection($this->subcategorias)
            ),
        ];
    }
}
