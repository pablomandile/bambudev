<?php

namespace App\Http\Resources;

use App\Models\DollarRate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Config;

/**
 * Una cotización del registro diario, con las claves en castellano como DolarApi.
 *
 * @mixin DollarRate
 */
class DollarRateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'tipo' => $this->type,
            'nombre' => DollarRate::TYPES[$this->type] ?? $this->type,
            'compra' => $this->buy,
            'venta' => $this->sell,
            'fuente' => $this->source,
            'fecha' => $this->date->format('Y-m-d'),
            'actualizado' => $this->captured_at->setTimezone(Config::string('bambudev.dollar.timezone'))->toIso8601String(),
        ];
    }
}
