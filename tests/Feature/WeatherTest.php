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

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::factory()->create();
        $this->branch = Branch::factory()->create([
            'tenant_id' => $tenant->id,
            'city' => 'Puebla',
            'state' => 'Puebla',
        ]);
    }

    public function test_weather_endpoint_returns_data(): void
    {
        config(['services.openweathermap.key' => 'test-key']);

        Http::fake([
            'api.openweathermap.org/*' => Http::response([
                'main' => ['temp' => 22.5, 'feels_like' => 21.0, 'humidity' => 65],
                'weather' => [['description' => 'cielo claro', 'icon' => '01d']],
                'name' => 'Puebla',
            ]),
        ]);

        $response = $this->getJson(route('api.weather', $this->branch));

        $response->assertOk();
        $response->assertJsonStructure(['temp', 'feels_like', 'humidity', 'description', 'icon', 'city']);
        $response->assertJson([
            'temp' => 23,
            'city' => 'Puebla',
        ]);
    }

    public function test_weather_is_cached_for_30_minutes(): void
    {
        config(['services.openweathermap.key' => 'test-key']);

        Http::fake([
            'api.openweathermap.org/*' => Http::response([
                'main' => ['temp' => 25.0, 'feels_like' => 24.0, 'humidity' => 50],
                'weather' => [['description' => 'nublado', 'icon' => '03d']],
                'name' => 'Puebla',
            ]),
        ]);

        // Primera llamada — hace request HTTP
        $this->getJson(route('api.weather', $this->branch))->assertOk();

        // Segunda llamada — debería usar cache (no hace HTTP)
        Http::fake([
            'api.openweathermap.org/*' => Http::response([], 500),
        ]);

        $response = $this->getJson(route('api.weather', $this->branch));
        $response->assertOk();
        $response->assertJson(['temp' => 25]);
    }

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

        Http::fake([
            'api.openweathermap.org/*' => Http::response([], 500),
        ]);

        $response = $this->getJson(route('api.weather', $this->branch));

        $response->assertStatus(503);
    }

    public function test_weather_uses_branch_city(): void
    {
        config(['services.openweathermap.key' => 'test-key']);

        Http::fake([
            'api.openweathermap.org/*' => Http::response([
                'main' => ['temp' => 20.0, 'feels_like' => 19.0, 'humidity' => 70],
                'weather' => [['description' => 'lluvia ligera', 'icon' => '10d']],
                'name' => 'Puebla',
            ]),
        ]);

        $this->getJson(route('api.weather', $this->branch));

        // Verificar que la query incluyó la ciudad de la branch
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'q=Puebla');
        });
    }

    public function test_weather_falls_back_to_default_city(): void
    {
        config(['services.openweathermap.key' => 'test-key']);

        $tenant = Tenant::factory()->create(['timezone' => 'America/Mexico_City']);
        $branch = Branch::factory()->create([
            'tenant_id' => $tenant->id,
            'city' => null,
            'address' => null,
        ]);

        Http::fake([
            'api.openweathermap.org/*' => Http::response([
                'main' => ['temp' => 18.0, 'feels_like' => 17.0, 'humidity' => 55],
                'weather' => [['description' => 'despejado', 'icon' => '01d']],
                'name' => 'Ciudad de Mexico',
            ]),
        ]);

        $response = $this->getJson(route('api.weather', $branch));
        $response->assertOk();

        Http::assertSent(function ($request) {
            $url = urldecode($request->url());
            return str_contains($url, 'Ciudad de Mexico');
        });
    }
}
