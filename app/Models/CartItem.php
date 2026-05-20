<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'cart_id',
        'product_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    // ── Relaciones ──────────────────────────────────────────────────────────

    public function carrito(): BelongsTo
    {
        return $this->belongsTo(Cart::class, 'cart_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
