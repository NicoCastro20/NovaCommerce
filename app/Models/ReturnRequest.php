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
        'no_me_gusta',
        'no_es_lo_que_esperaba',
        'cambio_opinion',
        'defectuoso',
        'otro',
    ];

    public const ETIQUETAS_MOTIVO = [
        'no_me_gusta'           => 'No me gusta',
        'no_es_lo_que_esperaba' => 'No es lo que esperaba',
        'cambio_opinion'        => 'He cambiado de opinión',
        'defectuoso'            => 'Defectuoso',
        'otro'                  => 'Otro',
    ];

    public const ETIQUETAS_ESTADO = [
        'aprobada' => 'Aprobada',
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

    public function scopeAprobadas(Builder $query): Builder
    {
        return $query->where('status', 'aprobada');
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
