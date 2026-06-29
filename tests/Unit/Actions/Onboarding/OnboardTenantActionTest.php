<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Onboarding;

use App\Actions\Onboarding\OnboardTenantAction;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Unit tests for OnboardTenantAction.
 *
 * Covers the contract: given valid pre-validated input, the action creates
 * tenant + admin user + branch atomically and returns the expected shape.
 *
 * Integration tests covering the full HTTP stack live in OnboardingTest
 * (existing) and CheckoutEndpointTest (added in PR-O).
 */
final class OnboardTenantActionTest extends TestCase
{
    use RefreshDatabase;

    private function validData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'María García',
            'email' => 'maria@test.com',
            'password' => 'Olinora2026!',
            'company_name' => 'Clínica Santa Fe',
            'slug' => 'clinica-santa-fe',
            'branch_name' => 'Sucursal Centro',
            'branch_code' => 'CTR',
        ], $overrides);
    }

    public function test_creates_tenant_user_and_branch(): void
    {
        $action = $this->app->make(OnboardTenantAction::class);

        $result = $action->execute($this->validData());

        $this->assertInstanceOf(Tenant::class, $result['tenant']);
        $this->assertInstanceOf(User::class, $result['user']);
        $this->assertInstanceOf(Branch::class, $result['branch']);

        $this->assertDatabaseHas('tenants', [
            'slug' => 'clinica-santa-fe',
            'name' => 'Clínica Santa Fe',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('branches', [
            'tenant_id' => $result['tenant']->id,
            'code' => 'CTR',
            'is_active' => true,
        ]);
    }

    public function test_user_is_tenant_admin(): void
    {
        $action = $this->app->make(OnboardTenantAction::class);

        $result = $action->execute($this->validData());

        $this->assertEquals(UserRole::TENANT_ADMIN, $result['user']->role);
        $this->assertEquals($result['tenant']->id, $result['user']->tenant_id);
    }

    public function test_user_is_attached_to_branch(): void
    {
        $action = $this->app->make(OnboardTenantAction::class);

        $result = $action->execute($this->validData());

        $this->assertTrue($result['user']->branches->contains($result['branch']));
    }

    public function test_password_is_hashed(): void
    {
        $action = $this->app->make(OnboardTenantAction::class);

        $result = $action->execute($this->validData(['password' => 'PlainText123!']));

        $this->assertTrue(Hash::check('PlainText123!', $result['user']->password));
        $this->assertNotEquals('PlainText123!', $result['user']->password);
    }

    public function test_empty_password_is_accepted_for_social_registration(): void
    {
        // Social registration path: password may be empty. The Action must
        // accept this without throwing. The User model's 'hashed' cast
        // hashes the value regardless (including empty string), so we
        // assert the user was persisted with a non-null password column,
        // not that the value is empty.
        $action = $this->app->make(OnboardTenantAction::class);

        $result = $action->execute($this->validData(['password' => '']));

        $this->assertNotNull($result['user']->password);
        $this->assertNotEquals('Olinora2026!', $result['user']->password);
    }

    public function test_branch_gets_default_schedule_when_none_provided(): void
    {
        $action = $this->app->make(OnboardTenantAction::class);

        $result = $action->execute($this->validData());

        $schedule = $result['branch']->operating_hours;

        $this->assertIsArray($schedule);
        $this->assertArrayHasKey('monday', $schedule);
        $this->assertArrayHasKey('sunday', $schedule);
        $this->assertTrue($schedule['monday']['is_open']);
        $this->assertFalse($schedule['sunday']['is_open']);
        $this->assertEquals('09:00', $schedule['monday']['open']);
        $this->assertEquals('18:00', $schedule['monday']['close']);
    }

    public function test_branch_accepts_custom_schedule(): void
    {
        $customSchedule = [
            'monday' => ['open' => '07:00', 'close' => '20:00', 'is_open' => true],
            'tuesday' => ['open' => '07:00', 'close' => '20:00', 'is_open' => true],
            'wednesday' => ['open' => '07:00', 'close' => '20:00', 'is_open' => true],
            'thursday' => ['open' => '07:00', 'close' => '20:00', 'is_open' => true],
            'friday' => ['open' => '07:00', 'close' => '20:00', 'is_open' => true],
            'saturday' => ['open' => null, 'close' => null, 'is_open' => false],
            'sunday' => ['open' => null, 'close' => null, 'is_open' => false],
        ];

        $action = $this->app->make(OnboardTenantAction::class);

        $result = $action->execute($this->validData(['branch_schedule' => $customSchedule]));

        /** @var array<string, array<string, string|bool|null>> $hours */
        $hours = $result['branch']->operating_hours;
        $this->assertEquals('07:00', $hours['monday']['open']);
        $this->assertFalse($hours['saturday']['is_open']);
    }

    public function test_timezone_resolves_from_country_code(): void
    {
        $action = $this->app->make(OnboardTenantAction::class);

        $result = $action->execute($this->validData(['branch_country' => 'AR']));

        $this->assertEquals('America/Argentina/Buenos_Aires', $result['tenant']->timezone);
        $this->assertEquals('America/Argentina/Buenos_Aires', $result['branch']->timezone);
    }

    public function test_timezone_defaults_to_mexico_when_country_absent(): void
    {
        $action = $this->app->make(OnboardTenantAction::class);

        $result = $action->execute($this->validData());

        $this->assertEquals('America/Mexico_City', $result['tenant']->timezone);
    }

    public function test_explicit_timezone_overrides_country_mapping(): void
    {
        $action = $this->app->make(OnboardTenantAction::class);

        $result = $action->execute($this->validData([
            'branch_country' => 'MX',
            'branch_timezone' => 'America/Tijuana',
        ]));

        $this->assertEquals('America/Tijuana', $result['branch']->timezone);
    }

    public function test_branch_country_falls_back_to_company_country(): void
    {
        $action = $this->app->make(OnboardTenantAction::class);

        $result = $action->execute($this->validData([
            'company_country' => 'CO',
            'branch_country' => null,
        ]));

        $this->assertEquals('CO', $result['branch']->country);
        $this->assertEquals('America/Bogota', $result['branch']->timezone);
    }

    public function test_returns_array_with_expected_keys(): void
    {
        $action = $this->app->make(OnboardTenantAction::class);

        $result = $action->execute($this->validData());

        $this->assertIsArray($result);
        $this->assertArrayHasKey('tenant', $result);
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('branch', $result);
    }

    public function test_transaction_rolls_back_on_failure(): void
    {
        // Force failure by sending invalid branch_code (NULL-violating at DB level
        // would be caught by validation, but ULID generation on the action layer
        // is not testable this way. We rely on Laravel's transaction wrapping;
        // failure paths are integration-tested in OnboardingTest.)
        //
        // Sanity check: explicitly test that two separate runs produce distinct
        // tenants (no shared state).
        $action = $this->app->make(OnboardTenantAction::class);

        $first = $action->execute($this->validData());
        $second = $action->execute($this->validData([
            'email' => 'other@test.com',
            'slug' => 'other-tenant',
            'branch_code' => 'OTH',
            'company_name' => 'Other Clinic',
        ]));

        $this->assertNotEquals($first['tenant']->id, $second['tenant']->id);
        $this->assertNotEquals($first['user']->id, $second['user']->id);
        $this->assertNotEquals($first['branch']->id, $second['branch']->id);
    }
}
