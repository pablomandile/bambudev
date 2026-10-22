<?php

namespace App\Services\Weather;

use App\Support\WeatherCode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * El tiempo actual en la Ciudad de Buenos Aires, según Open-Meteo.
 *
 * Se consulta desde el servidor y se comparte entre todos los visitantes: una
 * sola request cada `fresh` segundos, y el navegador nunca habla con terceros.
 * Si Open-Meteo falla devuelve lo último que funcionó, o null; nunca una excepción.
 */
class CabaWeather
{
    public const CACHE_KEY = 'weather:caba';

    /**
     * Tras una falla no se vuelve a preguntar por un rato. Hace falta porque
     * Cache::flexible guarda el null de una falla como si fuera un "no hay
     * nada", y sin esto cada visita reintentaría contra un servicio caído.
     */
    public const BACKOFF_KEY = 'weather:caba:backoff';

    public const BACKOFF_SECONDS = 300;

    /**
     * @return array{temp: int, feelsLike: int, max: int|null, min: int|null, code: int, isDay: bool, label: string, icon: string, place: string, updatedAt: string}|null
     */
    public function current(): ?array
    {
        if (Cache::has(self::BACKOFF_KEY)) {
            return Cache::get(self::CACHE_KEY);
        }

        return Cache::flexible(
            self::CACHE_KEY,
            [Config::integer('bambudev.weather.fresh'), Config::integer('bambudev.weather.stale')],
            // Si el refresco falla, se vuelve a guardar el dato anterior en vez de pisarlo con null.
            fn () => $this->fetch() ?? Cache::get(self::CACHE_KEY),
        );
    }

    /**
     * @return array{temp: int, feelsLike: int, max: int|null, min: int|null, code: int, isDay: bool, label: string, icon: string, place: string, updatedAt: string}|null
     */
    private function fetch(): ?array
    {
        try {
            $response = Http::timeout(3)
                ->connectTimeout(2)
                ->acceptJson()
                ->get(Config::string('bambudev.weather.url'), [
                    'latitude' => Config::float('bambudev.weather.latitude'),
                    'longitude' => Config::float('bambudev.weather.longitude'),
                    'timezone' => Config::string('bambudev.weather.timezone'),
                    'current' => 'temperature_2m,apparent_temperature,weather_code,is_day',
                    'daily' => 'temperature_2m_max,temperature_2m_min',
                    'forecast_days' => 1,
                ])
                ->throw();

            $current = $response->json('current');

            if (! is_array($current) || ! is_numeric($current['temperature_2m'] ?? null) || ! is_numeric($current['weather_code'] ?? null)) {
                throw new \UnexpectedValueException('Respuesta de Open-Meteo sin los datos actuales.');
            }

            $code = (int) $current['weather_code'];
            $isDay = (bool) ($current['is_day'] ?? true);

            return [
                'temp' => (int) round((float) $current['temperature_2m']),
                'feelsLike' => (int) round((float) ($current['apparent_temperature'] ?? $current['temperature_2m'])),
                'max' => $this->roundOrNull($response->json('daily.temperature_2m_max.0')),
                'min' => $this->roundOrNull($response->json('daily.temperature_2m_min.0')),
                'code' => $code,
                'isDay' => $isDay,
                ...WeatherCode::describe($code, $isDay),
                'place' => Config::string('bambudev.weather.label'),
                'updatedAt' => Carbon::now(Config::string('bambudev.weather.timezone'))->format('H:i'),
            ];
        } catch (Throwable $e) {
            Log::warning('No se pudo traer el tiempo de Open-Meteo.', ['excepcion' => $e->getMessage()]);
            Cache::put(self::BACKOFF_KEY, true, self::BACKOFF_SECONDS);

            return null;
        }
    }

    private function roundOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) round((float) $value) : null;
    }
}
