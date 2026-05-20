<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnRequest extends Model
{
    use HasFactory;

    protected $table = 'returns';

    public const MOTIVOS = [
        'producto_defectuoso',
        'no_coincide_descripcion',
        'producto_dañado',
        'error_en_pedido',
        'otro',
    ];

    public const ETIQUETAS_MOTIVO = [
        'producto_defectuoso'     => 'Producto defectuoso',
        'no_coincide_descripcion' => 'No coincide con la descripción',
        'producto_dañado'         => 'Producto dañado en el transporte',
        'error_en_pedido'         => 'Error en el pedido',
        'otro'                    => 'Otro motivo',
    ];

    public const ETIQUETAS_ESTADO = [
        'solicitada' => 'Solicitada',
        'aprobada'   => 'Aprobada',
        'rechazada'  => 'Rechazada',
    ];

    protected $fillable = [
        'order_id',
        'user_id',
        'reason',
        'description',
        'status',
        'admin_notes',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'reason'      => 'string',
            'status'      => 'string',
            'resolved_at' => 'datetime',
        ];
    }

    // ── Relaciones ──────────────────────────────────────────────────────────

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // ── Scopes ──────────────────────────────────────────────────────────────

    public function scopePendientes(Builder $query): Builder
    {
        return $query->where('status', 'solicitada');
    }

    public function scopeAprobadas(Builder $query): Builder
    {
        return $query->where('status', 'aprobada');
    }

    public function scopeRechazadas(Builder $query): Builder
    {
        return $query->where('status', 'rechazada');
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    public function etiquetaMotivo(): string
    {
        return self::ETIQUETAS_MOTIVO[$this->reason] ?? $this->reason;
    }

    public function etiquetaEstado(): string
    {
        return self::ETIQUETAS_ESTADO[$this->status] ?? $this->status;
    }
}
