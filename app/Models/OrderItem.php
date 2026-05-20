<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'seller_id',
        'product_name',
        'product_price',
        'quantity',
        'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'product_price' => 'decimal:2',
            'subtotal'      => 'decimal:2',
            'quantity'      => 'integer',
        ];
    }

    // ── Relaciones ──────────────────────────────────────────────────────────

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }
}
