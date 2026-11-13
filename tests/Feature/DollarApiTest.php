<?php

namespace Tests\Feature;

use App\Models\DollarRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\FakesDollarSources;
use Tests\TestCase;

class DollarApiTest extends TestCase
{
    use FakesDollarSources, RefreshDatabase;

    private const KEY = 'clave-de-prueba-de-mibilletera-0123456789';

    protected function setUp(): void
    {
        parent::setUp();

        config(['bambudev.api.keys' => [self::KEY => 'mibilletera']]);
        // Viernes 13/11/2026 a las 18 en Buenos Aires.
        $this->travelTo(Carbon::parse('2026-11-13 18:00', 'America/Argentina/Buenos_Aires'));
    }

    private function api(string $uri): TestResponse
    {
        return $this->getJson("/api/v1/{$uri}", ['X-Api-Key' => self::KEY]);
    }

    private function record(string $type, string $date, float $sell): DollarRate
    {
        return DollarRate::factory()->create([
            'type' => $type,
            'date' => $date,
            'buy' => $sell - 20,
            'sell' => $sell,
            'captured_at' => Carbon::parse("{$date} 17:00", 'America/Argentina/Buenos_Aires'),
        ]);
    }

    // — Clave —

    public function test_it_requires_a_key()
    {
        $this->getJson('/api/v1/dolares')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Falta la clave de la API o no es válida.']);
    }

    public function test_it_rejects_a_wrong_key()
    {
        $this->getJson('/api/v1/dolares', ['X-Api-Key' => 'otra-clave-que-no-existe-0123456789'])
            ->assertUnauthorized();
    }

    public function test_without_configured_keys_everything_is_rejected()
    {
        config(['bambudev.api.keys' => []]);

        $this->api('dolares')->assertUnauthorized();
    }

    public function test_keys_are_read_from_the_env_as_app_and_key_pairs()
    {
        putenv('API_KEYS=mibilletera:'.self::KEY.', corta:abc, rota');
        $config = require config_path('bambudev.php');
        putenv('API_KEYS');

        // La clave corta (menos de 20 caracteres) y la que no tiene app se descartan.
        $this->assertSame([self::KEY => 'mibilletera'], $config['api']['keys']);
    }

    // — Valor actual —

    public function test_it_lists_the_current_rates()
    {
        $this->fakeSources();

        $this->api('dolares')
            ->assertOk()
            ->assertJsonCount(8, 'cotizaciones')
            ->assertJsonPath('cotizaciones.0.tipo', 'oficial')
            ->assertJsonPath('cotizaciones.0.venta', 1570)
            ->assertJsonPath('cotizaciones.0.fuente', 'Banco Nación')
            ->assertJsonPath('cotizaciones.0.fecha', '2026-11-13')
            ->assertJsonPath('atribucion', 'Fuentes: Banco Nación, BCRA y DolarApi (https://dolarapi.com).');
    }

    public function test_it_returns_the_current_value_of_one_type()
    {
        $this->fakeSources();

        $this->api('dolares/tarjeta')
            ->assertOk()
            ->assertJson([
                'tipo' => 'tarjeta',
                'nombre' => 'Tarjeta',
                'compra' => 1976,
                'venta' => 2041,
                'fecha' => '2026-11-13',
            ]);
    }

    public function test_an_unknown_type_lists_the_valid_ones()
    {
        $this->api('dolares/inventado')
            ->assertNotFound()
            ->assertJsonPath('message', 'No existe el tipo «inventado».')
            ->assertJsonPath('tipos', array_keys(DollarRate::TYPES));
    }

    public function test_with_every_source_down_it_serves_the_last_recorded_value()
    {
        $this->fakeAllSourcesDown();
        $this->record('blue', '2026-11-12', 1570);

        $this->api('dolares/blue')
            ->assertOk()
            ->assertJson(['tipo' => 'blue', 'venta' => 1570, 'fecha' => '2026-11-12']);
    }

    // — Por fecha —

    public function test_it_returns_the_value_of_a_given_day()
    {
        $this->record('blue', '2026-11-12', 1570);
        $this->record('blue', '2026-11-13', 1575);

        $this->api('dolares/blue/2026-11-12')
            ->assertOk()
            ->assertJson([
                'tipo' => 'blue',
                'venta' => 1570,
                'fecha' => '2026-11-12',
                'fechaPedida' => '2026-11-12',
                'actualizado' => '2026-11-12T17:00:00-03:00',
            ]);
    }

    public function test_a_day_without_record_falls_back_to_the_previous_one()
    {
        // Viernes 6 registrado; sábado 7 y domingo 8 no.
        $this->record('blue', '2026-11-06', 1540);
        $this->record('blue', '2026-11-09', 1550);

        $this->api('dolares/blue/2026-11-08')
            ->assertOk()
            ->assertJson(['venta' => 1540, 'fecha' => '2026-11-06', 'fechaPedida' => '2026-11-08']);
    }

    public function test_a_day_before_the_first_record_is_not_found()
    {
        $this->record('blue', '2026-11-12', 1570);

        $this->api('dolares/blue/2026-11-01')->assertNotFound();
    }

    public function test_the_type_is_respected_when_looking_by_date()
    {
        $this->record('blue', '2026-11-12', 1570);
        $this->record('oficial', '2026-11-12', 1500);

        $this->api('dolares/oficial/2026-11-12')->assertOk()->assertJson(['tipo' => 'oficial', 'venta' => 1500]);
    }

    public function test_impossible_and_future_dates_are_rejected()
    {
        $this->api('dolares/blue/2026-02-31')->assertUnprocessable()->assertJsonValidationErrors('fecha');
        $this->api('dolares/blue/2026-11-14')->assertUnprocessable()->assertJsonValidationErrors('fecha');
    }

    // — Histórico —

    public function test_the_history_comes_back_ordered_within_the_range()
    {
        $this->record('blue', '2026-11-13', 1575);
        $this->record('blue', '2026-11-11', 1555);
        $this->record('blue', '2026-11-12', 1570);
        $this->record('blue', '2026-10-01', 1400);
        $this->record('oficial', '2026-11-12', 1500);

        $this->api('dolares/blue/historico?desde=2026-11-11&hasta=2026-11-13')
            ->assertOk()
            ->assertJson(['tipo' => 'blue', 'desde' => '2026-11-11', 'hasta' => '2026-11-13'])
            ->assertJsonCount(3, 'cotizaciones')
            ->assertJsonPath('cotizaciones.*.fecha', ['2026-11-11', '2026-11-12', '2026-11-13']);
    }

    public function test_the_history_defaults_to_the_last_thirty_days()
    {
        $this->record('blue', '2026-11-13', 1575);
        $this->record('blue', '2026-10-01', 1400);

        $this->api('dolares/blue/historico')
            ->assertOk()
            ->assertJson(['desde' => '2026-10-14', 'hasta' => '2026-11-13'])
            ->assertJsonCount(1, 'cotizaciones');
    }

    public function test_the_history_validates_the_range()
    {
        $this->api('dolares/blue/historico?desde=2026-11-13&hasta=2026-11-01')
            ->assertUnprocessable()->assertJsonValidationErrors('desde');
        $this->api('dolares/blue/historico?desde=2024-01-01&hasta=2026-11-13')
            ->assertUnprocessable()->assertJsonValidationErrors('desde');
        $this->api('dolares/blue/historico?desde=ayer')
            ->assertUnprocessable()->assertJsonValidationErrors('desde');
    }

    // — Límite —

    public function test_each_app_has_a_limit_per_minute()
    {
        config(['bambudev.api.per_minute' => 2]);
        $this->record('blue', '2026-11-12', 1570);

        $this->api('dolares/blue/2026-11-12')->assertOk();
        $this->api('dolares/blue/2026-11-12')->assertOk();
        $this->api('dolares/blue/2026-11-12')->assertTooManyRequests();
    }
}
