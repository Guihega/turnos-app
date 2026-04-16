<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_ok(): void
    {
        $response = $this->getJson('/health');

        $response->assertOk()
            ->assertJson([
                'status' => 'ok',
            ])
            ->assertJsonStructure([
                'status',
                'checks' => [
                    'database',
                    'redis',
                    'supervisor',
                ],
                'timestamp',
            ]);
    }

    public function test_health_endpoint_is_public(): void
    {
        // No auth required — should work without being logged in
        $response = $this->getJson('/health');

        $response->assertOk();
    }

    public function test_health_endpoint_shows_database_ok_when_connected(): void
    {
        $response = $this->getJson('/health');

        $response->assertOk()
            ->assertJsonPath('checks.database', 'ok');
    }

    public function test_health_endpoint_shows_redis_ok_when_connected(): void
    {
        $response = $this->getJson('/health');

        $response->assertOk()
            ->assertJsonPath('checks.redis', 'ok');
    }
}
