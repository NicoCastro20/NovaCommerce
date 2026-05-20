<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'avatar',
        'role',
        'nif_cif',
        'company_name',
        'is_premium',
        'premium_since',
        'premium_until',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_premium'        => 'boolean',
            'premium_since'     => 'datetime',
            'premium_until'     => 'datetime',
        ];
    }

    // ── Relaciones ──────────────────────────────────────────────────────────

    public function productos(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function resenas(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function listaDeseos(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    public function carrito(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    // ── Helpers de rol ──────────────────────────────────────────────────────

    public function esAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function esEmpresa(): bool
    {
        return $this->role === 'empresa';
    }

    public function esUsuario(): bool
    {
        return $this->role === 'usuario';
    }

    // ── Suscripción Premium ─────────────────────────────────────────────────

    /**
     * El usuario disfruta de los beneficios Premium mientras premium_until esté
     * en el futuro, independientemente de si is_premium sigue activo. Tras una
     * cancelación, is_premium pasa a false pero los beneficios se mantienen
     * hasta la fecha de expiración, tal y como anuncia el flujo de cancelación.
     */
    public function esPremium(): bool
    {
        return $this->premium_until !== null && $this->premium_until->isFuture();
    }

    public function diasRestantesPremium(): int
    {
        if ($this->premium_until === null || ! $this->premium_until->isFuture()) {
            return 0;
        }

        return (int) ceil(now()->diffInDays($this->premium_until, false));
    }

    public function activarPremium(): void
    {
        $this->is_premium    = true;
        $this->premium_since = now();
        $this->premium_until = now()->addYear();
        $this->save();
    }

    /**
     * Marca la suscripción como no renovable. premium_until se conserva para
     * que los beneficios sigan vigentes hasta esa fecha.
     */
    public function cancelarPremium(): void
    {
        $this->is_premium = false;
        $this->save();
    }
}
