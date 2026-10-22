<?php

namespace Tests\Feature;

use App\Services\Weather\CabaWeather;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WeatherTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function openMeteo(int $code = 0, int $isDay = 1, float $temp = 21.6): array
    {
        return [
            'current' => [
                'temperature_2m' => $temp,
                'apparent_temperature' => 20.4,
                'weather_code' => $code,
                'is_day' => $isDay,
            ],
            'daily' => [
                'temperature_2m_max' => [26.2],
                'temperature_2m_min' => [14.7],
            ],
        ];
    }

    private function fakeOpenMeteo(array $body, int $status = 200): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response($body, $status)]);
    }

    public function test_it_returns_the_current_weather_already_rounded_and_translated()
    {
        $this->fakeOpenMeteo($this->openMeteo(code: 2));

        $weather = app(CabaWeather::class)->current();

        $this->assertSame(22, $weather['temp']);
        $this->assertSame(20, $weather['feelsLike']);
        $this->assertSame(26, $weather['max']);
        $this->assertSame(15, $weather['min']);
        $this->assertSame('Parcialmente nublado', $weather['label']);
        $this->assertSame('ph-cloud-sun', $weather['icon']);
        $this->assertSame('CABA', $weather['place']);
        $this->assertTrue($weather['isDay']);
    }

    public function test_it_asks_for_buenos_aires_without_geolocation()
    {
        $this->fakeOpenMeteo($this->openMeteo());

        app(CabaWeather::class)->current();

        Http::assertSent(fn (Request $request) => $request['latitude'] == -34.6037
            && $request['longitude'] == -58.3816
            && $request['timezone'] === 'America/Argentina/Buenos_Aires');
    }

    public function test_at_night_the_sun_becomes_a_moon()
    {
        $this->fakeOpenMeteo($this->openMeteo(code: 0, isDay: 0));

        $this->assertSame('ph-moon', app(CabaWeather::class)->current()['icon']);
    }

    public function test_unknown_codes_fall_back_to_a_plain_cloud()
    {
        $this->fakeOpenMeteo($this->openMeteo(code: 42));

        $weather = app(CabaWeather::class)->current();

        $this->assertSame('—', $weather['label']);
        $this->assertSame('ph-cloud', $weather['icon']);
    }

    public function test_everyone_shares_one_request_while_it_is_fresh()
    {
        $this->fakeOpenMeteo($this->openMeteo());

        app(CabaWeather::class)->current();
        app(CabaWeather::class)->current();
        app(CabaWeather::class)->current();

        Http::assertSentCount(1);
    }

    public function test_a_failure_returns_null_logs_it_and_backs_off()
    {
        Log::spy();
        $this->fakeOpenMeteo(['error' => true], 500);

        $this->assertNull(app(CabaWeather::class)->current());
        $this->assertNull(app(CabaWeather::class)->current());

        // Con Open-Meteo caído no se le vuelve a preguntar en cada visita.
        Http::assertSentCount(1);
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_a_timeout_is_handled_like_any_other_failure()
    {
        Http::fake(fn () => throw new ConnectionException('Timeout'));

        $this->assertNull(app(CabaWeather::class)->current());
    }

    public function test_a_failed_refresh_keeps_the_last_good_weather()
    {
        // El refresco de Cache::flexible corre al instante en vez de después de la respuesta.
        $this->withoutDefer();
        // Primero responde bien; al refrescar, Open-Meteo está caído.
        Http::fake(['api.open-meteo.com/*' => Http::sequence()
            ->push($this->openMeteo(temp: 18))
            ->push([], 500)]);
        app(CabaWeather::class)->current();

        $this->travel(20)->minutes();
        $this->assertSame(18, app(CabaWeather::class)->current()['temp']);

        Http::assertSentCount(2);
        $this->assertSame(18, Cache::get(CabaWeather::CACHE_KEY)['temp']);
    }

    public function test_the_home_does_not_wait_for_the_weather()
    {
        $this->fakeOpenMeteo($this->openMeteo());

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->missing('weather')
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->where('weather.temp', 22)
                    ->where('weather.place', 'CABA')
                )
            );
    }

    public function test_the_home_still_works_when_open_meteo_is_down()
    {
        $this->fakeOpenMeteo([], 503);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload->where('weather', null))
            );
    }
}
