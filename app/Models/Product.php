<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
        'original_price',
        'offer_starts_at',
        'offer_ends_at',
        'offer_label',
    ];

    protected $appends = ['is_on_offer', 'discount_percentage'];

    protected function casts(): array
    {
        return [
            'price'           => 'decimal:2',
            'original_price'  => 'decimal:2',
            'is_active'       => 'boolean',
            'stock'           => 'integer',
            'type'            => 'string',
            'condition'       => 'string',
            'offer_starts_at' => 'datetime',
            'offer_ends_at'   => 'datetime',
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

    // ── Accessors de oferta ─────────────────────────────────────────────────

    protected function isOnOffer(): Attribute
    {
        return Attribute::make(
            get: function (): bool {
                if ($this->original_price === null) {
                    return false;
                }

                if ((float) $this->original_price <= (float) $this->price) {
                    return false;
                }

                $ahora = now();

                if ($this->offer_starts_at !== null && $this->offer_starts_at->gt($ahora)) {
                    return false;
                }

                if ($this->offer_ends_at !== null && $this->offer_ends_at->lt($ahora)) {
                    return false;
                }

                return true;
            },
        );
    }

    protected function discountPercentage(): Attribute
    {
        return Attribute::make(
            get: function (): ?int {
                if (! $this->is_on_offer) {
                    return null;
                }

                $original = (float) $this->original_price;
                $actual   = (float) $this->price;

                if ($original <= 0) {
                    return null;
                }

                return (int) round((($original - $actual) / $original) * 100);
            },
        );
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

    public function scopeOnOffer(Builder $query): Builder
    {
        $ahora = now();

        return $query
            ->whereNotNull('original_price')
            ->whereColumn('original_price', '>', 'price')
            ->where(function (Builder $q) use ($ahora) {
                $q->whereNull('offer_starts_at')->orWhere('offer_starts_at', '<=', $ahora);
            })
            ->where(function (Builder $q) use ($ahora) {
                $q->whereNull('offer_ends_at')->orWhere('offer_ends_at', '>=', $ahora);
            });
    }
}
