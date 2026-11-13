<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DollarRateResource;
use App\Models\DollarRate;
use App\Services\Dollar\DollarRates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;

/**
 * La API propia del dólar: el valor actual de cada tipo (el mismo del Inicio) y
 * el registro diario, para pedir la cotización de una fecha o un rango.
 */
class DollarController extends Controller
{
    private const ATTRIBUTION = 'Fuentes: Banco Nación, BCRA y DolarApi (https://dolarapi.com).';

    private const MAX_RANGE_DAYS = 366;

    public function __construct(private DollarRates $dollar) {}

    /**
     * GET /api/v1/dolares — todos los tipos, valor actual.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'cotizaciones' => $this->current()->values(),
            'atribucion' => self::ATTRIBUTION,
        ]);
    }

    /**
     * GET /api/v1/dolares/{tipo} — un tipo, valor actual.
     */
    public function show(string $tipo): JsonResponse
    {
        $this->ensureType($tipo);

        $rate = $this->current()->get($tipo);

        abort_if($rate === null, 404, "Todavía no hay cotización de «{$tipo}».");

        return response()->json($rate);
    }

    /**
     * GET /api/v1/dolares/{tipo}/{fecha} — el valor de ese día. Si ese día no hubo
     * (fin de semana, feriado), el último anterior, con la fecha real en `fecha`.
     */
    public function onDate(string $tipo, string $fecha): JsonResponse
    {
        $this->ensureType($tipo);
        $date = $this->parseDate($fecha, 'fecha');

        $rate = DollarRate::forType($tipo)
            ->whereDate('date', '<=', $date)
            ->orderByDesc('date')
            ->first();

        abort_if($rate === null, 404, "No hay registro de «{$tipo}» al {$date->format('Y-m-d')}.");

        return response()->json([
            ...(new DollarRateResource($rate))->resolve(),
            'fechaPedida' => $date->format('Y-m-d'),
        ]);
    }

    /**
     * GET /api/v1/dolares/{tipo}/historico?desde=&hasta= — el registro de un rango,
     * del más viejo al más nuevo. Por defecto, los últimos 30 días.
     */
    public function history(Request $request, string $tipo): JsonResponse
    {
        $this->ensureType($tipo);

        $until = $request->filled('hasta') ? $this->parseDate((string) $request->query('hasta'), 'hasta') : $this->today();
        $from = $request->filled('desde') ? $this->parseDate((string) $request->query('desde'), 'desde') : $until->copy()->subDays(30);

        if ($from->greaterThan($until)) {
            throw ValidationException::withMessages(['desde' => 'La fecha «desde» no puede ser posterior a «hasta».']);
        }

        if ($from->diffInDays($until) > self::MAX_RANGE_DAYS) {
            throw ValidationException::withMessages(['desde' => 'El rango no puede pasar de '.self::MAX_RANGE_DAYS.' días.']);
        }

        $rates = DollarRate::forType($tipo)
            ->whereDate('date', '>=', $from->format('Y-m-d'))
            ->whereDate('date', '<=', $until->format('Y-m-d'))
            ->orderBy('date')
            ->get();

        return response()->json([
            'tipo' => $tipo,
            'desde' => $from->format('Y-m-d'),
            'hasta' => $until->format('Y-m-d'),
            'cotizaciones' => DollarRateResource::collection($rates)->resolve(),
            'atribucion' => self::ATTRIBUTION,
        ]);
    }

    /**
     * El valor actual por tipo: el del Inicio (en caché, 15 min) y, si de algún tipo
     * no hay, el último del registro.
     *
     * @return Collection<string, non-empty-array<string, mixed>>
     */
    private function current(): Collection
    {
        $live = collect($this->dollar->current()['rates'] ?? [])
            ->mapWithKeys(fn (array $row) => [$row['id'] => [
                'tipo' => $row['id'],
                'nombre' => $row['name'],
                'compra' => $row['buy'],
                'venta' => $row['sell'],
                'fuente' => $row['source'],
                'fecha' => $row['date'],
                'actualizado' => $row['capturedAt'],
            ]]);

        return collect(DollarRate::TYPES)
            ->map(fn (string $name, string $type) => $live->get($type) ?? $this->latestRecorded($type))
            ->filter();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function latestRecorded(string $type): ?array
    {
        $rate = DollarRate::forType($type)->orderByDesc('date')->first();

        return $rate ? (new DollarRateResource($rate))->resolve() : null;
    }

    private function ensureType(string $tipo): void
    {
        if (! array_key_exists($tipo, DollarRate::TYPES)) {
            abort(response()->json([
                'message' => "No existe el tipo «{$tipo}».",
                'tipos' => array_keys(DollarRate::TYPES),
            ], 404));
        }
    }

    private function parseDate(string $value, string $field): Carbon
    {
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)
            ? Carbon::createFromFormat('!Y-m-d', $value, $this->timezone())
            : null;

        // createFromFormat acepta "2026-02-31" corriéndolo a marzo: se descarta.
        if (! $date || $date->format('Y-m-d') !== $value) {
            throw ValidationException::withMessages([$field => "La fecha «{$field}» tiene que ser AAAA-MM-DD."]);
        }

        if ($date->greaterThan($this->today())) {
            throw ValidationException::withMessages([$field => "La fecha «{$field}» no puede ser futura."]);
        }

        return $date;
    }

    private function today(): Carbon
    {
        return Carbon::today($this->timezone());
    }

    private function timezone(): string
    {
        return Config::string('bambudev.dollar.timezone');
    }
}
