<?php

namespace Tests\Feature;

use App\Models\DollarRate;
use App\Services\Dollar\DollarRates;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\FakesDollarSources;
use Tests\TestCase;

class DollarTest extends TestCase
{
    use FakesDollarSources, RefreshDatabase;

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

    // — Registro diario —

    public function test_each_reading_is_recorded_once_per_type_and_day()
    {
        $this->fakeSources();
        $today = now('America/Argentina/Buenos_Aires')->format('Y-m-d');

        app(DollarRates::class)->refresh();
        app(DollarRates::class)->refresh();

        // Dos lecturas el mismo día: se actualiza la fila, no se duplica.
        $this->assertDatabaseCount('dollar_rates', 8);
        $oficial = DollarRate::forType('oficial')->sole();
        $this->assertSame($today, $oficial->date->format('Y-m-d'));
        $this->assertSame([1520.0, 1570.0, 'Banco Nación'], [$oficial->buy, $oficial->sell, $oficial->source]);
        $this->assertSame(2041.0, DollarRate::forType('tarjeta')->sole()->sell);
    }

    public function test_a_reading_updates_a_row_saved_through_the_model()
    {
        // El modelo y el upsert del servicio tienen que escribir la fecha igual,
        // o el mismo día queda dos veces.
        DollarRate::factory()->create(['type' => 'blue', 'date' => now('America/Argentina/Buenos_Aires'), 'sell' => 1]);
        $this->fakeSources();

        app(DollarRates::class)->refresh();

        $this->assertSame(1575.0, DollarRate::forType('blue')->sole()->sell);
    }

    public function test_the_last_reading_of_the_day_wins()
    {
        Http::fake([
            'dolarapi.com/v1/ambito/*' => Http::response($this->ambito()),
            'dolarapi.com/v1/dolares*' => Http::sequence()
                ->push($this->dolarApi())
                ->push(collect($this->dolarApi())->map(fn ($d) => $d['casa'] === 'blue' ? [...$d, 'venta' => 1600] : $d)->all()),
            'api.bcra.gob.ar/*' => Http::response($this->bcra()),
            'www.bna.com.ar/*' => Http::response($this->bna()),
        ]);

        app(DollarRates::class)->refresh();
        app(DollarRates::class)->refresh();

        $this->assertSame(1600.0, DollarRate::forType('blue')->sole()->sell);
    }

    public function test_rows_are_dated_by_their_source_not_by_the_reading()
    {
        // Sábado al mediodía: el BNA y el BCRA siguen mostrando el viernes.
        $this->travelTo(Carbon::parse('2026-11-14 12:00', 'America/Argentina/Buenos_Aires'));
        $this->fakeSources([
            'api.bcra.gob.ar/*' => Http::response($this->bcra('2026-11-13')),
            'www.bna.com.ar/*' => Http::response($this->bna('13/11/2026')),
        ]);

        app(DollarRates::class)->refresh();

        $dates = DollarRate::all()->mapWithKeys(fn (DollarRate $r) => [$r->type => $r->date->format('Y-m-d')]);
        $this->assertSame('2026-11-13', $dates['oficial']);
        $this->assertSame('2026-11-13', $dates['tarjeta']);
        $this->assertSame('2026-11-13', $dates['mayorista']);
        $this->assertSame('2026-11-14', $dates['blue']);
    }

    public function test_rows_reused_from_the_cache_are_not_recorded_again()
    {
        // Ámbito responde la primera vez y después se cae.
        $this->fakeSources([
            'dolarapi.com/v1/ambito/*' => Http::sequence()->push($this->ambito())->whenEmpty(Http::response([], 500)),
        ]);
        app(DollarRates::class)->refresh();
        DollarRate::query()->delete();

        // Su fila sale del caché, pero no se vuelve a escribir.
        $rates = app(DollarRates::class)->refresh();

        $this->assertContains('blue_ambito', array_column($rates['rates'], 'id'));
        $this->assertDatabaseCount('dollar_rates', 7);
        $this->assertDatabaseMissing('dollar_rates', ['type' => 'blue_ambito']);
    }

    public function test_a_database_failure_does_not_take_the_dollar_off_the_home()
    {
        Log::spy();
        $this->fakeSources();
        Schema::drop('dollar_rates');

        $this->assertCount(8, app(DollarRates::class)->current()['rates']);
        Log::shouldHaveReceived('warning')->with('No se pudo guardar el registro diario del dólar.', \Mockery::any())->once();
    }

    public function test_the_record_command_reads_even_while_backing_off()
    {
        $this->fakeSources();
        Cache::put(DollarRates::BACKOFF_KEY, true, DollarRates::BACKOFF_SECONDS);

        $this->artisan('dollar:record')
            ->expectsOutputToContain('8 cotizaciones al día')
            ->assertSuccessful();

        $this->assertDatabaseCount('dollar_rates', 8);
        // Y deja el caché al día para el Inicio.
        $this->assertCount(8, Cache::get(DollarRates::CACHE_KEY)['rates']);
    }

    public function test_the_record_command_fails_when_every_source_is_down()
    {
        $this->fakeAllSourcesDown();

        $this->artisan('dollar:record')->assertFailed();
        $this->assertDatabaseCount('dollar_rates', 0);
    }

    public function test_the_record_command_runs_every_hour()
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains((string) $event->command, 'dollar:record'));

        $this->assertNotNull($event);
        $this->assertSame('0 * * * *', $event->expression);
    }
}
