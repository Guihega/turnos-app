<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->branch = Branch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'city' => 'Puebla',
            'state' => 'Puebla',
            'country' => 'MX',
        ]);
    }

    private function fakeWeatherResponse(array $overrides = []): array
    {
        return array_merge([
            'main' => ['temp' => 22.5, 'feels_like' => 21.0, 'humidity' => 65],
            'weather' => [['description' => 'cielo claro', 'icon' => '01d']],
            'name' => 'Puebla',
        ], $overrides);
    }

    // ─── Respuesta básica ───

    public function test_weather_endpoint_returns_data(): void
    {
        config(['services.openweathermap.key' => 'test-key']);

        Http::fake([
            'api.openweathermap.org/*' => Http::response($this->fakeWeatherResponse()),
        ]);

        $response = $this->getJson(route('api.weather', $this->branch));

        $response->assertOk();
        $response->assertJsonStructure(['temp', 'feels_like', 'humidity', 'description', 'icon', 'city']);
        $response->assertJson(['temp' => 23, 'city' => 'Puebla']);
    }

    // ─── Cache ───

    public function test_weather_is_cached_for_30_minutes(): void
    {
        config(['services.openweathermap.key' => 'test-key']);

        Http::fake([
            'api.openweathermap.org/*' => Http::response($this->fakeWeatherResponse(['main' => ['temp' => 25.0, 'feels_like' => 24.0, 'humidity' => 50]])),
        ]);

        $this->getJson(route('api.weather', $this->branch))->assertOk();

        // Segunda llamada con API caída — usa cache
        Http::fake(['api.openweathermap.org/*' => Http::response([], 500)]);

        $response = $this->getJson(route('api.weather', $this->branch));
        $response->assertOk();
        $response->assertJson(['temp' => 25]);
    }

    // ─── Errores ───

    public function test_weather_returns_503_without_api_key(): void
    {
        config(['services.openweathermap.key' => null]);
        Cache::forget("weather:branch:{$this->branch->id}");

        $response = $this->getJson(route('api.weather', $this->branch));
        $response->assertStatus(503);
        $response->assertJson(['error' => 'API key no configurada']);
    }

    public function test_weather_handles_api_failure_gracefully(): void
    {
        config(['services.openweathermap.key' => 'test-key']);
        Cache::forget("weather:branch:{$this->branch->id}");

        Http::fake(['api.openweathermap.org/*' => Http::response([], 500)]);

        $response = $this->getJson(route('api.weather', $this->branch));
        $response->assertStatus(503);
    }

    // ─── Prioridad 1: Coordenadas ───

    public function test_weather_uses_coordinates_when_available(): void
    {
        config(['services.openweathermap.key' => 'test-key']);

        $branch = Branch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'city' => 'Puebla',
            'country' => 'MX',
            'latitude' => 19.0414,
            'longitude' => -98.2063,
        ]);

        Http::fake([
            'api.openweathermap.org/*' => Http::response($this->fakeWeatherResponse()),
        ]);

        $this->getJson(route('api.weather', $branch));

        Http::assertSent(function ($request) {
            $url = $request->url();

            return str_contains($url, 'lat=19.0414') && str_contains($url, 'lon=-98.2063');
        });
    }

    // ─── Prioridad 2: Ciudad + País ───

    public function test_weather_uses_city_and_country(): void
    {
        config(['services.openweathermap.key' => 'test-key']);

        Http::fake([
            'api.openweathermap.org/*' => Http::response($this->fakeWeatherResponse()),
        ]);

        $this->getJson(route('api.weather', $this->branch));

        Http::assertSent(function ($request) {
            $url = urldecode($request->url());

            return str_contains($url, 'Puebla,Puebla,MX');
        });
    }

    public function test_weather_uses_country_without_hardcoding_mx(): void
    {
        config(['services.openweathermap.key' => 'test-key']);

        $branch = Branch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'city' => 'Lima',
            'state' => 'Lima',
            'country' => 'PE',
        ]);

        Http::fake([
            'api.openweathermap.org/*' => Http::response($this->fakeWeatherResponse(['name' => 'Lima'])),
        ]);

        $this->getJson(route('api.weather', $branch));

        Http::assertSent(function ($request) {
            $url = urldecode($request->url());

            return str_contains($url, 'Lima,Lima,PE');
        });
    }

    // ─── Prioridad 3: Fallback por timezone ───

    public function test_weather_falls_back_to_timezone_city(): void
    {
        config(['services.openweathermap.key' => 'test-key']);

        $tenant = Tenant::factory()->create(['timezone' => 'America/Bogota']);
        $branch = Branch::factory()->create([
            'tenant_id' => $tenant->id,
            'city' => null,
            'state' => null,
            'address' => null,
            'country' => '',
            'latitude' => null,
            'longitude' => null,
            'timezone' => 'America/Bogota',
        ]);

        Http::fake([
            'api.openweathermap.org/*' => Http::response($this->fakeWeatherResponse(['name' => 'Bogota'])),
        ]);

        $response = $this->getJson(route('api.weather', $branch));
        $response->assertOk();

        Http::assertSent(function ($request) {
            $url = urldecode($request->url());

            return str_contains($url, 'Bogota,CO');
        });
    }

    // ─── Coordenadas tienen prioridad sobre ciudad ───

    public function test_coordinates_take_priority_over_city(): void
    {
        config(['services.openweathermap.key' => 'test-key']);

        $branch = Branch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'city' => 'Puebla',
            'country' => 'MX',
            'latitude' => 19.0414,
            'longitude' => -98.2063,
        ]);

        Http::fake([
            'api.openweathermap.org/*' => Http::response($this->fakeWeatherResponse()),
        ]);

        $this->getJson(route('api.weather', $branch));

        // Debe usar lat/lon, NO q=Puebla
        Http::assertSent(function ($request) {
            $url = $request->url();

            return str_contains($url, 'lat=') && ! str_contains($url, 'q=Puebla');
        });
    }
}
