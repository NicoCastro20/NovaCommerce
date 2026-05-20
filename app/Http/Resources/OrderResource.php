<?php

namespace App\Http\Resources;

use App\Models\Order;
use App\Models\ReturnRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Order $this */
        $devolucion = $this->relationLoaded('devolucion') ? $this->devolucion : null;

        return [
            'id'                   => $this->id,
            'order_number'         => $this->order_number,
            'status'               => $this->status,
            'subtotal'             => (float) $this->subtotal,
            'shipping_cost'        => (float) $this->shipping_cost,
            'envio_premium'        => (bool) $this->envio_premium,
            'total'                => (float) $this->total,
            'shipping_address'     => $this->shipping_address,
            'shipping_city'        => $this->shipping_city,
            'shipping_postal_code' => $this->shipping_postal_code,
            'shipping_country'     => $this->shipping_country,
            'payment_method'       => $this->payment_method,
            'notes'                => $this->notes,
            'delivered_at'         => $this->delivered_at,
            'return_deadline'      => $this->fechaLimiteDevolucion(),
            'can_be_cancelled'     => $this->puedeCancelarse(),
            'can_be_returned'      => $this->puedeDevolverse(),
            'created_at'           => $this->created_at,
            'updated_at'           => $this->updated_at,
            'buyer'                => $this->whenLoaded('comprador', fn () => [
                'id'    => $this->comprador->id,
                'name'  => $this->comprador->name,
                'email' => $this->comprador->email,
            ]),
            'items'                => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id'            => $item->id,
                'product_id'    => $item->product_id,
                'seller_id'     => $item->seller_id,
                'product_name'  => $item->product_name,
                'product_price' => (float) $item->product_price,
                'quantity'      => $item->quantity,
                'subtotal'      => (float) $item->subtotal,
                'product'       => $item->relationLoaded('producto') && $item->producto ? [
                    'id'   => $item->producto->id,
                    'name' => $item->producto->name,
                    'slug' => $item->producto->slug,
                ] : null,
            ])),
            'return'               => $devolucion ? [
                'id'           => $devolucion->id,
                'reason'       => $devolucion->reason,
                'reason_label' => ReturnRequest::ETIQUETAS_MOTIVO[$devolucion->reason] ?? $devolucion->reason,
                'description'  => $devolucion->description,
                'status'       => $devolucion->status,
                'status_label' => ReturnRequest::ETIQUETAS_ESTADO[$devolucion->status] ?? $devolucion->status,
                'admin_notes'  => $devolucion->admin_notes,
                'resolved_at'  => $devolucion->resolved_at,
                'created_at'   => $devolucion->created_at,
            ] : null,
        ];
    }
}
