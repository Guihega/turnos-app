<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.geonames.username' => 'test-user']);
    }

    // ─── Estados ───

    public function test_states_endpoint_returns_data(): void
    {
        Http::fake([
            'api.geonames.org/countryInfoJSON*' => Http::response([
                'geonames' => [['geonameId' => 3996063]],
            ]),
            'api.geonames.org/childrenJSON*' => Http::response([
                'geonames' => [
                    ['geonameId' => 1, 'name' => 'Puebla', 'adminCode1' => 'PUE'],
                    ['geonameId' => 2, 'name' => 'Oaxaca', 'adminCode1' => 'OAX'],
                    ['geonameId' => 3, 'name' => 'Chiapas', 'adminCode1' => 'CHP'],
                ],
            ]),
        ]);

        $response = $this->getJson('/api/geo/states/MX');

        $response->assertOk();
        $response->assertJsonCount(3);
        $response->assertJsonFragment(['name' => 'Puebla']);
    }

    public function test_states_are_sorted_alphabetically(): void
    {
        Http::fake([
            'api.geonames.org/countryInfoJSON*' => Http::response([
                'geonames' => [['geonameId' => 3996063]],
            ]),
            'api.geonames.org/childrenJSON*' => Http::response([
                'geonames' => [
                    ['geonameId' => 1, 'name' => 'Zacatecas', 'adminCode1' => 'ZAC'],
                    ['geonameId' => 2, 'name' => 'Aguascalientes', 'adminCode1' => 'AGU'],
                    ['geonameId' => 3, 'name' => 'Morelos', 'adminCode1' => 'MOR'],
                ],
            ]),
        ]);

        $response = $this->getJson('/api/geo/states/MX');

        $data = $response->json();
        $this->assertEquals('Aguascalientes', $data[0]['name']);
        $this->assertEquals('Zacatecas', $data[2]['name']);
    }

    public function test_states_are_cached_for_30_days(): void
    {
        Http::fake([
            'api.geonames.org/*' => Http::response([
                'geonames' => [['geonameId' => 3996063]],
            ]),
        ]);

        // Primera llamada
        $this->getJson('/api/geo/states/MX');

        // Segunda llamada con API caída — debe usar cache
        Http::fake(['api.geonames.org/*' => Http::response([], 500)]);

        $response = $this->getJson('/api/geo/states/MX');
        $response->assertOk();
    }

    public function test_states_returns_503_without_username(): void
    {
        config(['services.geonames.username' => null]);

        $response = $this->getJson('/api/geo/states/MX');
        $response->assertStatus(503);
    }

    // ─── Ciudades ───

    public function test_cities_endpoint_returns_data(): void
    {
        Http::fake([
            'api.geonames.org/childrenJSON*' => Http::response([
                'geonames' => [
                    ['geonameId' => 10, 'name' => 'Puebla de Zaragoza', 'lat' => '19.04', 'lng' => '-98.2'],
                    ['geonameId' => 11, 'name' => 'Tehuacán', 'lat' => '18.46', 'lng' => '-97.39'],
                ],
            ]),
        ]);

        $response = $this->getJson('/api/geo/cities/MX/12345');

        $response->assertOk();
        $response->assertJsonCount(2);
        $response->assertJsonFragment(['name' => 'Puebla de Zaragoza']);
    }

    public function test_cities_include_coordinates(): void
    {
        Http::fake([
            'api.geonames.org/childrenJSON*' => Http::response([
                'geonames' => [
                    ['geonameId' => 10, 'name' => 'Lima', 'lat' => '-12.04', 'lng' => '-77.03'],
                ],
            ]),
        ]);

        $response = $this->getJson('/api/geo/cities/PE/99999');

        $response->assertOk();
        $response->assertJsonFragment(['lat' => '-12.04', 'lng' => '-77.03']);
    }

    // ─── Búsqueda ───

    public function test_search_returns_results(): void
    {
        Http::fake([
            'api.geonames.org/searchJSON*' => Http::response([
                'geonames' => [
                    ['name' => 'Puebla', 'adminName1' => 'Puebla', 'lat' => '19.04', 'lng' => '-98.2'],
                    ['name' => 'Puebla de los Ángeles', 'adminName1' => 'Puebla', 'lat' => '19.05', 'lng' => '-98.21'],
                ],
            ]),
        ]);

        $response = $this->getJson('/api/geo/search/MX?q=Puebla');

        $response->assertOk();
        $response->assertJsonCount(2);
    }

    public function test_search_requires_minimum_2_characters(): void
    {
        $response = $this->getJson('/api/geo/search/MX?q=P');
        $response->assertOk();
        $response->assertJsonCount(0);
    }

    // ─── País normalizado ───

    public function test_country_code_is_uppercased(): void
    {
        Http::fake([
            'api.geonames.org/countryInfoJSON*' => Http::response([
                'geonames' => [['geonameId' => 3996063]],
            ]),
            'api.geonames.org/childrenJSON*' => Http::response([
                'geonames' => [['geonameId' => 1, 'name' => 'Lima', 'adminCode1' => 'LIM']],
            ]),
        ]);

        // Enviar en minúsculas — debe funcionar igual
        $response = $this->getJson('/api/geo/states/pe');
        $response->assertOk();
    }

    // ─── Error de API ───

    public function test_handles_api_failure_gracefully(): void
    {
        Cache::forget('geo:states:MX');

        Http::fake(['api.geonames.org/*' => Http::response([], 500)]);

        $response = $this->getJson('/api/geo/states/MX');
        $response->assertStatus(503);
    }
}
