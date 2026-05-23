<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'product_id' => $this->product_id,
            'rating'     => (int) $this->rating,
            'comment'    => $this->comment,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'user'       => $this->whenLoaded('usuario', fn () => [
                'id'     => $this->usuario->id,
                'name'   => $this->usuario->name,
                'role'   => $this->usuario->role,
                'avatar' => $this->usuario->avatarUrl(),
            ]),
        ];
    }
}
