<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'category_id',
        'name',
        'slug',
        'description',
        'price',
        'stock',
        'is_active',
        'type',
        'condition',
    ];

    protected function casts(): array
    {
        return [
            'price'     => 'decimal:2',
            'is_active' => 'boolean',
            'stock'     => 'integer',
            'type'      => 'string',
            'condition' => 'string',
        ];
    }

    // ── Relaciones ──────────────────────────────────────────────────────────

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function imagenes(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function imagenPrincipal(): HasMany
    {
        return $this->hasMany(ProductImage::class)->where('is_primary', true);
    }

    public function resenas(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function wishlistEntradas(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    // ── Scopes ──────────────────────────────────────────────────────────────

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopePorCategoria(Builder $query, int $categoryId): Builder
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeBuscar(Builder $query, string $termino): Builder
    {
        return $query->where(function (Builder $q) use ($termino) {
            $q->where('name', 'like', "%{$termino}%")
              ->orWhere('description', 'like', "%{$termino}%");
        });
    }

    public function scopeNuevo(Builder $query): Builder
    {
        return $query->where('type', 'nuevo');
    }

    public function scopeSegundaMano(Builder $query): Builder
    {
        return $query->where('type', 'segunda_mano');
    }
}
