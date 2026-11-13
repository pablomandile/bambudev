<?php

namespace Tests\Feature;

use App\Services\Dollar\DollarRates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DollarTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, array<string, mixed>>
     */
    private function dolarApi(): array
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
    private function ambito(): array
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
     * @return array<string, mixed>
     */
    private function bcra(): array
    {
        return [
            'status' => 200,
            'results' => [
                'fecha' => Carbon::now('America/Argentina/Buenos_Aires')->format('Y-m-d'),
                'detalle' => [
                    ['codigoMoneda' => 'EUR', 'descripcion' => 'EURO', 'tipoPase' => 1.1028, 'tipoCotizacion' => 1702.1718],
                    ['codigoMoneda' => 'USD', 'descripcion' => 'DOLAR E.E.U.U.', 'tipoPase' => 0, 'tipoCotizacion' => 1543.5],
                ],
            ],
        ];
    }

    private function bna(): string
    {
        // La estructura real de la página de Personas del BNA (13/11/2026).
        return <<<'HTML'
            <html><body>
            <div class="tab-pane fade in active" id="billetes">
                <table class="table cotizacion">
                    <thead><tr><th class="fechaCot">13/11/2026</th><th>Compra</th><th>Venta</th></tr></thead>
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
    private function fakeSources(array $overrides = []): void
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

    public function test_it_combines_the_sources_into_ordered_rows()
    {
        $this->fakeSources();

        $rates = collect(app(DollarRates::class)->current()['rates'])->keyBy('id');

        $this->assertSame(
            ['oficial', 'tarjeta', 'mayorista', 'blue', 'blue_ambito', 'mep', 'ccl', 'cripto'],
            $rates->keys()->all(),
        );

        // El oficial sale del BNA, con "1.520,00" ya convertido y su hora.
        $oficial = $rates['oficial'];
        $this->assertSame(1520.0, $oficial['buy']);
        $this->assertSame(1570.0, $oficial['sell']);
        $this->assertSame('Banco Nación', $oficial['source']);
        $this->assertSame('15:02', $oficial['updatedAt']);

        // El mayorista sale del BCRA: un solo valor y fecha sin hora.
        $mayorista = $rates['mayorista'];
        $this->assertNull($mayorista['buy']);
        $this->assertSame(1543.5, $mayorista['sell']);
        $this->assertSame('BCRA', $mayorista['source']);
        $this->assertSame(now('America/Argentina/Buenos_Aires')->format('d/m'), $mayorista['updatedAt']);

        // El resto, de DolarApi; el blue de Ámbito aparte, para comparar.
        $this->assertSame([1555.0, 1575.0, 'DolarApi'], [$rates['blue']['buy'], $rates['blue']['sell'], $rates['blue']['source']]);
        $this->assertSame([1550.0, 1570.0, 'Ámbito vía DolarApi'], [$rates['blue_ambito']['buy'], $rates['blue_ambito']['sell'], $rates['blue_ambito']['source']]);
        $this->assertSame(1578.2, $rates['mep']['sell']);
        $this->assertSame(1671.4, $rates['ccl']['sell']);
        $this->assertSame(1669.05, $rates['cripto']['sell']);
    }

    public function test_the_card_rate_is_calculated_from_the_official()
    {
        $this->fakeSources();

        $tarjeta = collect(app(DollarRates::class)->current()['rates'])->firstWhere('id', 'tarjeta');

        $this->assertSame(1976.0, $tarjeta['buy']);   // 1520 × 1,30
        $this->assertSame(2041.0, $tarjeta['sell']);  // 1570 × 1,30
        $this->assertSame('Calculado (oficial × 1,30)', $tarjeta['source']);
    }

    public function test_when_the_bna_page_changes_the_official_falls_back_to_dolarapi()
    {
        Log::spy();
        $this->fakeSources(['www.bna.com.ar/*' => Http::response('<html><body>Rediseño</body></html>')]);

        $rates = collect(app(DollarRates::class)->current()['rates'])->keyBy('id');

        $this->assertSame([1515.0, 1565.0, 'DolarApi'], [$rates['oficial']['buy'], $rates['oficial']['sell'], $rates['oficial']['source']]);
        // Y el tarjeta se calcula sobre ese oficial.
        $this->assertSame(2034.5, $rates['tarjeta']['sell']);
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_when_the_bcra_is_down_the_wholesale_falls_back_to_dolarapi()
    {
        Log::spy();
        $this->fakeSources(['api.bcra.gob.ar/*' => Http::response([], 500)]);

        $mayorista = collect(app(DollarRates::class)->current()['rates'])->firstWhere('id', 'mayorista');

        $this->assertSame(1534.5, $mayorista['buy']);
        $this->assertSame(1543.5, $mayorista['sell']);
        $this->assertSame('DolarApi', $mayorista['source']);
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_everyone_shares_one_round_of_requests_while_it_is_fresh()
    {
        $this->fakeSources();

        app(DollarRates::class)->current();
        app(DollarRates::class)->current();
        app(DollarRates::class)->current();

        Http::assertSentCount(4);
    }

    public function test_a_total_failure_returns_null_logs_it_and_backs_off()
    {
        Log::spy();
        $this->fakeSources([
            'dolarapi.com/v1/ambito/*' => Http::response([], 500),
            'dolarapi.com/v1/dolares*' => Http::response([], 500),
            'api.bcra.gob.ar/*' => Http::response([], 500),
            'www.bna.com.ar/*' => Http::response([], 500),
        ]);

        $this->assertNull(app(DollarRates::class)->current());
        $this->assertNull(app(DollarRates::class)->current());

        // Con todo caído no se vuelve a preguntar en cada visita.
        Http::assertSentCount(4);
        Log::shouldHaveReceived('warning')
            ->with('No se pudo traer el valor del dólar de ninguna fuente.')
            ->once();
    }

    public function test_a_failed_refresh_keeps_the_last_good_rates()
    {
        // El refresco de Cache::flexible corre al instante en vez de después de la respuesta.
        $this->withoutDefer();
        // Primero responde todo bien; al refrescar, todo está caído.
        Http::fake([
            'dolarapi.com/v1/ambito/*' => Http::sequence()->push($this->ambito())->push([], 500),
            'dolarapi.com/v1/dolares*' => Http::sequence()->push($this->dolarApi())->push([], 500),
            'api.bcra.gob.ar/*' => Http::sequence()->push($this->bcra())->push([], 500),
            'www.bna.com.ar/*' => Http::sequence()->push($this->bna())->push([], 500),
        ]);
        app(DollarRates::class)->current();

        $this->travel(20)->minutes();
        $rates = collect(app(DollarRates::class)->current()['rates']);

        $this->assertSame(1570.0, $rates->firstWhere('id', 'oficial')['sell']);
        Http::assertSentCount(8);
        $this->assertSame(1570.0, collect(Cache::get(DollarRates::CACHE_KEY)['rates'])->firstWhere('id', 'oficial')['sell']);
    }

    public function test_a_source_that_comes_back_empty_keeps_its_last_good_row()
    {
        $this->withoutDefer();
        // DolarApi y el BCRA responden siempre; el BNA y Ámbito solo la primera vez.
        Http::fake([
            'dolarapi.com/v1/ambito/*' => Http::sequence()->push($this->ambito())->whenEmpty(Http::response([], 500)),
            'dolarapi.com/v1/dolares*' => Http::response($this->dolarApi()),
            'api.bcra.gob.ar/*' => Http::response($this->bcra()),
            'www.bna.com.ar/*' => Http::sequence()->push($this->bna())->whenEmpty(Http::response([], 500)),
        ]);
        app(DollarRates::class)->current();

        $this->travel(20)->minutes();
        // La visita que dispara el refresco todavía ve el valor viejo; lo nuevo queda en el cache.
        app(DollarRates::class)->current();
        $rates = collect(Cache::get(DollarRates::CACHE_KEY)['rates'])->keyBy('id');

        // El BNA caído tiene respaldo: el oficial pasa a DolarApi. Ámbito no
        // tiene: su blue queda con los últimos valores que funcionaron.
        $this->assertSame('DolarApi', $rates['oficial']['source']);
        $this->assertSame(
            [1550.0, 1570.0, 'Ámbito vía DolarApi'],
            [$rates['blue_ambito']['buy'], $rates['blue_ambito']['sell'], $rates['blue_ambito']['source']],
        );
    }

    public function test_the_home_does_not_wait_for_the_dollar()
    {
        $this->fakeSources();

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->missing('dollar')
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->where('dollar.rates.0.id', 'oficial')
                    ->where('dollar.rates.0.source', 'Banco Nación')
                    ->has('dollar.rates', 8)
                )
            );
    }

    public function test_the_home_still_works_when_every_source_is_down()
    {
        $this->fakeSources([
            'dolarapi.com/v1/ambito/*' => Http::response([], 500),
            'dolarapi.com/v1/dolares*' => Http::response([], 500),
            'api.bcra.gob.ar/*' => Http::response([], 500),
            'www.bna.com.ar/*' => Http::response([], 500),
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload->where('dollar', null))
            );
    }
}
