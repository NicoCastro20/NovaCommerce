<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class Order extends Model
{
    use HasFactory;

    public const DIAS_VENTANA_DEVOLUCION = 14;

    protected $fillable = [
        'user_id',
        'order_number',
        'status',
        'subtotal',
        'shipping_cost',
        'envio_premium',
        'total',
        'shipping_address',
        'shipping_city',
        'shipping_postal_code',
        'shipping_country',
        'payment_method',
        'payment_id',
        'notes',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal'      => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'envio_premium' => 'boolean',
            'total'         => 'decimal:2',
            'delivered_at'  => 'datetime',
        ];
    }

    // ── Boot ────────────────────────────────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Order $order) {
            if (empty($order->order_number)) {
                $order->order_number = static::generarNumeroPedido();
            }
        });

        // Cuando el estado cambie a 'entregado', sellamos delivered_at
        // automáticamente para empezar a contar el plazo de devolución.
        static::updating(function (Order $order): void {
            if ($order->isDirty('status')
                && $order->status === 'entregado'
                && $order->delivered_at === null) {
                $order->delivered_at = now();
            }
        });
    }

    private static function generarNumeroPedido(): string
    {
        $anio = now()->year;
        $secuencia = str_pad(
            (string) (static::whereYear('created_at', $anio)->count() + 1),
            5,
            '0',
            STR_PAD_LEFT
        );

        return "NC-{$anio}-{$secuencia}";
    }

    // ── Relaciones ──────────────────────────────────────────────────────────

    public function comprador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function devolucion(): HasOne
    {
        return $this->hasOne(ReturnRequest::class, 'order_id');
    }

    // ── Estado de cancelación / devolución ──────────────────────────────────

    public function puedeCancelarse(): bool
    {
        return in_array($this->status, ['pendiente', 'pagado'], true);
    }

    public function puedeDevolverse(): bool
    {
        if ($this->status !== 'entregado') {
            return false;
        }
        if ($this->delivered_at === null) {
            return false;
        }
        if ($this->delivered_at->lt(Carbon::now()->subDays(self::DIAS_VENTANA_DEVOLUCION))) {
            return false;
        }

        if ($this->relationLoaded('devolucion')) {
            return $this->devolucion === null;
        }
        return $this->devolucion()->doesntExist();
    }

    public function fechaLimiteDevolucion(): ?Carbon
    {
        return $this->delivered_at?->copy()->addDays(self::DIAS_VENTANA_DEVOLUCION);
    }

    /**
     * Días enteros que aún restan dentro del plazo de devolución. Devuelve
     * `null` si el pedido no está entregado, y `0` si el plazo ya expiró.
     */
    public function diasRestantesDevolucion(): ?int
    {
        $limite = $this->fechaLimiteDevolucion();
        if ($limite === null) {
            return null;
        }
        $ahora = Carbon::now();
        if ($limite->lessThanOrEqualTo($ahora)) {
            return 0;
        }
        return (int) ceil($ahora->floatDiffInDays($limite));
    }

    // ── Operaciones de stock ────────────────────────────────────────────────

    /**
     * Cancela el pedido y devuelve el stock de cada item. Si algún producto
     * era de segunda mano, lo reactiva (is_active = true) ya que normalmente
     * los productos de segunda mano se desactivan al venderse.
     */
    public function cancelar(): void
    {
        DB::transaction(function (): void {
            $this->loadMissing('items.producto');

            foreach ($this->items as $item) {
                $producto = $item->producto;
                if ($producto === null) {
                    continue;
                }

                $producto->increment('stock', $item->quantity);

                if ($producto->type === 'segunda_mano' && ! $producto->is_active) {
                    $producto->is_active = true;
                    $producto->save();
                }
            }

            $this->status = 'cancelado';
            $this->save();
        });
    }

    /**
     * Aprueba la devolución del pedido: pasa a 'devuelto', restaura el stock
     * y marca resolved_at en la solicitud asociada.
     */
    public function aprobarDevolucion(?string $adminNotes = null): void
    {
        DB::transaction(function () use ($adminNotes): void {
            $this->loadMissing(['items.producto', 'devolucion']);

            foreach ($this->items as $item) {
                $producto = $item->producto;
                if ($producto === null) {
                    continue;
                }

                $producto->increment('stock', $item->quantity);

                if ($producto->type === 'segunda_mano' && ! $producto->is_active) {
                    $producto->is_active = true;
                    $producto->save();
                }
            }

            $this->status = 'devuelto';
            $this->save();

            if ($this->devolucion !== null) {
                $this->devolucion->status = 'aprobada';
                $this->devolucion->resolved_at = now();
                if ($adminNotes !== null) {
                    $this->devolucion->admin_notes = $adminNotes;
                }
                $this->devolucion->save();
            }
        });
    }
}
