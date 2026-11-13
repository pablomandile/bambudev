<?php

namespace App\Services\Dollar;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * El valor del dólar en la Argentina, combinando fuentes.
 *
 * El oficial minorista sale de la página de Banco Nación, el mayorista de la API
 * del BCRA, el tarjeta se calcula sobre el oficial, y el blue, MEP, CCL, cripto y
 * el blue de Ámbito salen de DolarApi. Si BNA o el BCRA fallan, ese valor sale de
 * DolarApi; si tampoco está, queda el último que funcionó.
 *
 * Como el tiempo (ver CabaWeather), se consulta desde el servidor y se comparte
 * entre todos: una tanda de requests cada `fresh` segundos. Nunca lanza excepciones.
 *
 * @phpstan-type Rate array{id: string, name: string, buy: float|null, sell: float|null, source: string, updatedAt: string}
 * @phpstan-type Rates array{rates: list<Rate>, updatedAt: string}
 */
class DollarRates
{
    public const CACHE_KEY = 'dollar:rates';

    /** Tras fallar todas las fuentes no se vuelve a preguntar por un rato (ver CabaWeather). */
    public const BACKOFF_KEY = 'dollar:rates:backoff';

    public const BACKOFF_SECONDS = 300;

    /** Las filas de DolarApi que se muestran tal cual: id => [casa en DolarApi, nombre]. */
    private const FROM_DOLARAPI = [
        'blue' => ['blue', 'Blue'],
        'blue_ambito' => [null, 'Blue (Ámbito)'],
        'mep' => ['bolsa', 'MEP'],
        'ccl' => ['contadoconliqui', 'CCL'],
        'cripto' => ['cripto', 'Cripto'],
    ];

    /**
     * @return Rates|null
     */
    public function current(): ?array
    {
        if (Cache::has(self::BACKOFF_KEY)) {
            return Cache::get(self::CACHE_KEY);
        }

        return Cache::flexible(
            self::CACHE_KEY,
            [Config::integer('bambudev.dollar.fresh'), Config::integer('bambudev.dollar.stale')],
            // Si el refresco falla, se vuelve a guardar el dato anterior en vez de pisarlo con null.
            fn () => $this->fetch() ?? Cache::get(self::CACHE_KEY),
        );
    }

    /**
     * @return Rates|null
     */
    private function fetch(): ?array
    {
        $dolarapi = Config::string('bambudev.dollar.dolarapi');

        $responses = Http::pool(fn (Pool $pool) => [
            $pool->as('dolares')->timeout(3)->connectTimeout(2)->acceptJson()->get("{$dolarapi}/dolares"),
            $pool->as('ambito')->timeout(3)->connectTimeout(2)->acceptJson()->get("{$dolarapi}/ambito/dolares/blue"),
            $pool->as('bcra')->timeout(3)->connectTimeout(2)->acceptJson()->get(Config::string('bambudev.dollar.bcra')),
            $pool->as('bna')->timeout(3)->connectTimeout(2)
                ->withUserAgent(Config::string('bambudev.dollar.user_agent'))
                ->get(Config::string('bambudev.dollar.bna')),
        ]);

        $dolares = $this->attempt('DolarApi', fn () => $this->parseDolarApi($this->body($responses['dolares'])));
        $ambito = $this->attempt('DolarApi (Ámbito)', fn () => $this->parseDolarApiRate($this->json($responses['ambito'])));
        $bcra = $this->attempt('el BCRA', fn () => $this->parseBcra($this->json($responses['bcra'])));
        $bna = $this->attempt('Banco Nación', fn () => $this->parseBna($this->body($responses['bna'])));

        $cached = Cache::get(self::CACHE_KEY)['rates'] ?? null;
        /** @var Collection<string, Rate> $previous */
        $previous = collect(is_array($cached) ? $cached : [])->keyBy('id');
        $rows = [];

        // Oficial: Banco Nación, si no DolarApi.
        $rows['oficial'] = $bna
            ? $this->row('oficial', 'Oficial', $bna['buy'], $bna['sell'], 'Banco Nación', $bna['at'])
            : $this->fromDolarApi($dolares, 'oficial', 'oficial', 'Oficial');

        // Tarjeta: siempre calculado sobre el oficial que haya quedado.
        if ($oficial = $rows['oficial'] ?? $previous->get('oficial')) {
            $factor = Config::float('bambudev.dollar.card_factor');
            $rows['tarjeta'] = [
                ...$oficial,
                'id' => 'tarjeta',
                'name' => 'Tarjeta',
                'buy' => $oficial['buy'] === null ? null : round($oficial['buy'] * $factor, 2),
                'sell' => $oficial['sell'] === null ? null : round($oficial['sell'] * $factor, 2),
                'source' => 'Calculado (oficial × '.number_format($factor, 2, ',', '.').')',
            ];
        }

        // Mayorista: BCRA (solo un valor, el de referencia), si no DolarApi.
        $rows['mayorista'] = $bcra
            ? $this->row('mayorista', 'Mayorista', null, $bcra['sell'], 'BCRA', $bcra['at'])
            : $this->fromDolarApi($dolares, 'mayorista', 'mayorista', 'Mayorista');

        foreach (self::FROM_DOLARAPI as $id => [$casa, $name]) {
            $rows[$id] = $casa === null
                ? ($ambito ? $this->row($id, $name, $ambito['buy'], $ambito['sell'], 'Ámbito vía DolarApi', $ambito['at']) : null)
                : $this->fromDolarApi($dolares, $casa, $id, $name);
        }

        $order = ['oficial', 'tarjeta', 'mayorista', 'blue', 'blue_ambito', 'mep', 'ccl', 'cripto'];
        $rates = [];
        $fresh = 0;

        foreach ($order as $id) {
            if ($row = $rows[$id] ?? null) {
                $rates[] = $row;
                $fresh++;
            } elseif ($old = $previous->get($id)) {
                // Sin fuente ni respaldo: queda lo último que funcionó, con su hora vieja.
                $rates[] = $old;
            }
        }

        if ($fresh === 0) {
            Log::warning('No se pudo traer el valor del dólar de ninguna fuente.');
            Cache::put(self::BACKOFF_KEY, true, self::BACKOFF_SECONDS);

            return null;
        }

        return [
            'rates' => $rates,
            'updatedAt' => $this->now()->format('H:i'),
        ];
    }

    /**
     * Corre un parseo; si falla, lo loguea y devuelve null para que entre el respaldo.
     *
     * @template T
     *
     * @param  callable(): T  $parse
     * @return T|null
     */
    private function attempt(string $source, callable $parse): mixed
    {
        try {
            return $parse();
        } catch (Throwable $e) {
            Log::warning("No se pudo traer el valor del dólar de {$source}.", ['excepcion' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * En el pool, una falla de conexión llega como excepción en vez de lanzarse.
     */
    private function body(mixed $response): string
    {
        if (! $response instanceof Response) {
            throw $response instanceof Throwable ? $response : new RuntimeException('Sin respuesta.');
        }

        return $response->throw()->body();
    }

    private function json(mixed $response): mixed
    {
        return json_decode($this->body($response), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, array{buy: float|null, sell: float|null, at: Carbon}>
     */
    private function parseDolarApi(string $body): array
    {
        $data = json_decode($body, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($data) || ! array_is_list($data)) {
            throw new \UnexpectedValueException('Respuesta de DolarApi sin la lista de dólares.');
        }

        $byCasa = [];

        foreach ($data as $item) {
            if (is_array($item) && is_string($item['casa'] ?? null)) {
                try {
                    $byCasa[$item['casa']] = $this->parseDolarApiRate($item);
                } catch (Throwable) {
                    // Una casa rota no tira abajo a las demás.
                }
            }
        }

        return $byCasa;
    }

    /**
     * @return array{buy: float|null, sell: float|null, at: Carbon}
     */
    private function parseDolarApiRate(mixed $item): array
    {
        if (! is_array($item) || ! is_numeric($item['venta'] ?? null)) {
            throw new \UnexpectedValueException('Cotización de DolarApi sin venta.');
        }

        return [
            'buy' => is_numeric($item['compra'] ?? null) ? (float) $item['compra'] : null,
            'sell' => (float) $item['venta'],
            'at' => is_string($item['fechaActualizacion'] ?? null)
                ? Carbon::parse($item['fechaActualizacion'])->setTimezone($this->timezone())
                : $this->now(),
        ];
    }

    /**
     * El USD de las Estadísticas Cambiarias: `tipoCotizacion` son pesos por dólar
     * (el mayorista de referencia). Trae solo la fecha, sin hora.
     *
     * @return array{sell: float, at: Carbon}
     */
    private function parseBcra(mixed $data): array
    {
        $detalle = is_array($data) ? ($data['results']['detalle'] ?? []) : [];
        $usd = null;

        foreach (is_array($detalle) ? $detalle : [] as $currency) {
            if (is_array($currency) && ($currency['codigoMoneda'] ?? null) === 'USD') {
                $usd = $currency;
                break;
            }
        }

        if (! is_array($usd) || ! is_numeric($usd['tipoCotizacion'] ?? null) || (float) $usd['tipoCotizacion'] <= 0) {
            throw new \UnexpectedValueException('Respuesta del BCRA sin el dólar.');
        }

        $date = $data['results']['fecha'] ?? null;

        return [
            'sell' => (float) $usd['tipoCotizacion'],
            'at' => is_string($date)
                ? Carbon::createFromFormat('!Y-m-d', $date, $this->timezone())
                : $this->now()->startOfDay(),
        ];
    }

    /**
     * Banco Nación no tiene API: se lee la tabla de billetes de su página de Personas.
     * Si cambian el HTML se lanza una excepción en vez de guardar datos falsos.
     *
     * @return array{buy: float, sell: float, at: Carbon}
     */
    private function parseBna(string $html): array
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($dom);

        foreach ($xpath->query('//div[@id="billetes"]//table//tbody/tr') ?: [] as $tr) {
            if (! $tr instanceof DOMElement) {
                continue;
            }

            $cells = [];

            foreach ($xpath->query('td', $tr) ?: [] as $td) {
                if ($td instanceof DOMElement) {
                    $cells[] = trim($td->textContent);
                }
            }

            if (count($cells) >= 3 && $this->normalize($cells[0]) === 'dolar u.s.a') {
                $at = $this->now();

                foreach ($xpath->query('//div[@id="billetes"]//*[contains(@class, "legal")]') ?: [] as $legal) {
                    if ($legal instanceof DOMElement && preg_match('/(\d{1,2}):(\d{2})/', $legal->textContent, $m)) {
                        $at = $at->setTime((int) $m[1], (int) $m[2]);
                        break;
                    }
                }

                return ['buy' => $this->number($cells[1]), 'sell' => $this->number($cells[2]), 'at' => $at];
            }
        }

        throw new \UnexpectedValueException('No está la tabla de billetes: puede haber cambiado la página del BNA.');
    }

    private function normalize(string $text): string
    {
        return trim(str_replace('*', '', mb_strtolower($text)));
    }

    /**
     * "1.520,00" -> 1520.0
     */
    private function number(string $text): float
    {
        $clean = str_replace(['.', ','], ['', '.'], trim($text));

        if (! is_numeric($clean) || (float) $clean <= 0) {
            throw new \UnexpectedValueException("Número inválido en la página del BNA: «{$text}».");
        }

        return (float) $clean;
    }

    /**
     * @param  array<string, array{buy: float|null, sell: float|null, at: Carbon}>|null  $dolares
     * @return Rate|null
     */
    private function fromDolarApi(?array $dolares, string $casa, string $id, string $name): ?array
    {
        $rate = $dolares[$casa] ?? null;

        return $rate ? $this->row($id, $name, $rate['buy'], $rate['sell'], 'DolarApi', $rate['at']) : null;
    }

    /**
     * @return Rate
     */
    private function row(string $id, string $name, ?float $buy, ?float $sell, string $source, Carbon $at): array
    {
        $dateOnly = $at->format('H:i:s') === '00:00:00';

        return [
            'id' => $id,
            'name' => $name,
            'buy' => $buy,
            'sell' => $sell,
            'source' => $source,
            // De hoy y con hora: "15:02"; si no, el día: "12/11".
            'updatedAt' => $at->isSameDay($this->now()) && ! $dateOnly ? $at->format('H:i') : $at->format('d/m'),
        ];
    }

    private function timezone(): string
    {
        return Config::string('bambudev.dollar.timezone');
    }

    private function now(): Carbon
    {
        return Carbon::now($this->timezone());
    }
}
