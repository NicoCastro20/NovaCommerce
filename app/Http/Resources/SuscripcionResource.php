<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class SuscripcionResource extends JsonResource
{
    public const PRECIO_ANUAL = 50;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $this */
        $activa = $this->esPremium();

        return [
            'is_premium'           => (bool) $this->is_premium,
            'activa'               => $activa,
            'premium_since'        => $this->premium_since,
            'premium_until'        => $this->premium_until,
            'premium_since_legible' => $this->formatear($this->premium_since),
            'premium_until_legible' => $this->formatear($this->premium_until),
            'dias_restantes'       => $this->diasRestantesPremium(),
            'precio'               => self::PRECIO_ANUAL,
        ];
    }

    private function formatear(?Carbon $fecha): ?string
    {
        if ($fecha === null) {
            return null;
        }

        $meses = [
            1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
            5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
            9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
        ];

        return sprintf('%d de %s de %d', $fecha->day, $meses[$fecha->month], $fecha->year);
    }
}
