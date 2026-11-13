<?php

namespace Tests\Concerns;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Respuestas falsas de las cuatro fuentes del dólar (DolarApi, su blue de Ámbito,
 * el BCRA y la página del BNA), con la forma real que tenían el 13/11/2026.
 */
trait FakesDollarSources
{
    /**
     * @return array<int, array<string, mixed>>
     */
    protected function dolarApi(): array
    {
        $at = Carbon::now()->toISOString();
        $casas = [
            // Distintos a los del BNA y el BCRA, para notar cuándo entra el respaldo.
            'oficial' => [1515, 1565],
            'blue' => [1555, 1575],
            'bolsa' => [1577.6, 1578.2],
            'contadoconliqui' => [1670.6, 1671.4],
            'mayorista' => [1534.5, 1543.5],
            'cripto' => [1664.99, 1669.05],
            'tarjeta' => [1969.5, 2034.5],
        ];

        return collect($casas)->map(fn ($values, $casa) => [
            'moneda' => 'USD',
            'casa' => $casa,
            'nombre' => ucfirst($casa),
            'compra' => $values[0],
            'venta' => $values[1],
            'fechaActualizacion' => $at,
        ])->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function ambito(): array
    {
        return [
            'moneda' => 'USD',
            'casa' => 'blue',
            'compra' => 1550,
            'venta' => 1570,
            'fechaActualizacion' => Carbon::now()->toISOString(),
        ];
    }

    /**
     * @param  string|null  $date  AAAA-MM-DD; por defecto, hoy en la Argentina.
     * @return array<string, mixed>
     */
    protected function bcra(?string $date = null): array
    {
        return [
            'status' => 200,
            'results' => [
                'fecha' => $date ?? Carbon::now('America/Argentina/Buenos_Aires')->format('Y-m-d'),
                'detalle' => [
                    ['codigoMoneda' => 'EUR', 'descripcion' => 'EURO', 'tipoPase' => 1.1028, 'tipoCotizacion' => 1702.1718],
                    ['codigoMoneda' => 'USD', 'descripcion' => 'DOLAR E.E.U.U.', 'tipoPase' => 0, 'tipoCotizacion' => 1543.5],
                ],
            ],
        ];
    }

    /**
     * @param  string|null  $date  DD/MM/AAAA, como lo muestra el BNA; por defecto, hoy.
     */
    protected function bna(?string $date = null): string
    {
        $date ??= Carbon::now('America/Argentina/Buenos_Aires')->format('d/m/Y');

        return <<<HTML
            <html><body>
            <div class="tab-pane fade in active" id="billetes">
                <table class="table cotizacion">
                    <thead><tr><th class="fechaCot">{$date}</th><th>Compra</th><th>Venta</th></tr></thead>
                    <tbody>
                        <tr><td class="tit">Dolar U.S.A</td><td>1.520,00</td><td>1.570,00</td></tr>
                        <tr><td class="tit">Euro</td><td>1.670,00</td><td>1.770,00</td></tr>
                        <tr><td class="tit">Real *</td><td>25.500,00</td><td>28.500,00</td></tr>
                    </tbody>
                </table>
                <div class="legal">Hora Actualización: 15:02</div>
                <div class="legal">(*) cotización cada 100 unidades.</div>
            </div>
            </body></html>
            HTML;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function fakeSources(array $overrides = []): void
    {
        Http::fake([
            'dolarapi.com/v1/ambito/*' => Http::response($this->ambito()),
            'dolarapi.com/v1/dolares*' => Http::response($this->dolarApi()),
            'api.bcra.gob.ar/*' => Http::response($this->bcra()),
            'www.bna.com.ar/*' => Http::response($this->bna()),
            'api.open-meteo.com/*' => Http::response([], 503),
            ...$overrides,
        ]);
    }

    protected function fakeAllSourcesDown(): void
    {
        $this->fakeSources([
            'dolarapi.com/v1/ambito/*' => Http::response([], 500),
            'dolarapi.com/v1/dolares*' => Http::response([], 500),
            'api.bcra.gob.ar/*' => Http::response([], 500),
            'www.bna.com.ar/*' => Http::response([], 500),
        ]);
    }
}
