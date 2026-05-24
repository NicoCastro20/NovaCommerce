<?php

namespace App\Http\Resources;

use App\Models\ReturnRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReturnResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $statusBadge = $this->status === 'aprobada' ? 'success' : 'neutral';

        return [
            'id'            => $this->id,
            'order_id'      => $this->order_id,
            'user_id'       => $this->user_id,
            'reason'        => $this->reason,
            'reason_label'  => ReturnRequest::ETIQUETAS_MOTIVO[$this->reason] ?? $this->reason,
            'description'   => $this->description,
            'status'        => $this->status,
            'status_label'  => ReturnRequest::ETIQUETAS_ESTADO[$this->status] ?? $this->status,
            'status_badge'  => $statusBadge,
            'admin_notes'   => $this->admin_notes,
            'resolved_at'   => $this->resolved_at,
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
            'order'         => $this->whenLoaded('pedido', fn () => [
                'id'           => $this->pedido->id,
                'order_number' => $this->pedido->order_number,
                'status'       => $this->pedido->status,
                'total'        => (float) $this->pedido->total,
                'delivered_at' => $this->pedido->delivered_at,
                'items'        => $this->pedido->relationLoaded('items')
                    ? $this->pedido->items->map(fn ($item) => [
                        'id'            => $item->id,
                        'product_id'    => $item->product_id,
                        'seller_id'     => $item->seller_id,
                        'product_name'  => $item->product_name,
                        'product_price' => (float) $item->product_price,
                        'quantity'      => $item->quantity,
                        'subtotal'      => (float) $item->subtotal,
                    ])
                    : null,
            ]),
            'customer'      => $this->whenLoaded('cliente', fn () => [
                'id'    => $this->cliente->id,
                'name'  => $this->cliente->name,
                'email' => $this->cliente->email,
            ]),
        ];
    }
}
