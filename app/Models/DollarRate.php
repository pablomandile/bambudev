<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\DollarRateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Una cotización del registro diario: el último valor de un tipo de dólar en un día.
 *
 * @property int $id
 * @property string $type
 * @property CarbonImmutable $date
 * @property float|null $buy
 * @property float|null $sell
 * @property string $source
 * @property CarbonImmutable $captured_at
 */
class DollarRate extends Model
{
    /** @use HasFactory<DollarRateFactory> */
    use HasFactory;

    /** Los tipos, con el id que usan el servicio, la tabla y la API. */
    public const TYPES = [
        'oficial' => 'Oficial',
        'tarjeta' => 'Tarjeta',
        'mayorista' => 'Mayorista',
        'blue' => 'Blue',
        'blue_ambito' => 'Blue (Ámbito)',
        'mep' => 'MEP',
        'ccl' => 'CCL',
        'cripto' => 'Cripto',
    ];

    protected $fillable = ['type', 'date', 'buy', 'sell', 'source', 'captured_at'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'buy' => 'float',
            'sell' => 'float',
            'captured_at' => 'datetime',
        ];
    }

    /**
     * Siempre AAAA-MM-DD: el cast `date` guardaría "2026-11-13 00:00:00" y en SQLite
     * no coincidiría con lo que escribe el upsert del servicio, ni con un BETWEEN.
     *
     * @return Attribute<CarbonImmutable, mixed>
     */
    protected function date(): Attribute
    {
        return Attribute::make(set: fn (mixed $value) => Carbon::parse($value)->format('Y-m-d'));
    }

    /**
     * Siempre en UTC: Eloquent guarda la hora tal cual, sin pasarla a la zona de
     * la app, y una hora argentina volvería corrida tres horas.
     *
     * @return Attribute<CarbonImmutable, mixed>
     */
    protected function capturedAt(): Attribute
    {
        return Attribute::make(set: fn (mixed $value) => Carbon::parse($value)->utc()->format('Y-m-d H:i:s'));
    }

    /**
     * @param  Builder<DollarRate>  $query
     */
    public function scopeForType(Builder $query, string $type): void
    {
        $query->where('type', $type);
    }
}
