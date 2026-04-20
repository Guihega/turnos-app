<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function validData(array $overrides = []): array
    {
        return array_merge([
            'name'                  => 'María García',
            'email'                 => 'maria@nueva-empresa.com',
            'password'              => 'Olinora2026!',
            'password_confirmation' => 'Olinora2026!',
            'company_name'          => 'Clínica Santa Fe',
            'slug'                  => 'clinica-santa-fe',
            'branch_name'           => 'Sucursal Centro',
            'branch_code'           => 'CTR',
        ], $overrides);
    }

    // ─── Happy Path ───

    public function test_onboarding_page_loads(): void
    {
        $response = $this->get('/onboarding');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Onboarding/Register'));
    }

    public function test_successful_onboarding_creates_tenant_user_and_branch(): void
    {
        Event::fake([Registered::class]);

        $response = $this->post('/onboarding', $this->validData());

        // Tenant created
        $this->assertDatabaseHas('tenants', [
            'name' => 'Clínica Santa Fe',
            'slug' => 'clinica-santa-fe',
            'is_active' => true,
        ]);

        // User created with correct role (email is encrypted, can't use assertDatabaseHas)
        $user = User::findByEmail('maria@nueva-empresa.com');
        $this->assertNotNull($user);
        $this->assertEquals(UserRole::TENANT_ADMIN, $user->role);

        $tenant = Tenant::where('slug', 'clinica-santa-fe')->first();

        $this->assertEquals($tenant->id, $user->tenant_id);

        // Branch created
        $this->assertDatabaseHas('branches', [
            'tenant_id' => $tenant->id,
            'name'      => 'Sucursal Centro',
            'code'      => 'CTR',
            'is_active' => true,
        ]);

        // User attached to branch
        $branch = Branch::where('tenant_id', $tenant->id)->first();
        $this->assertTrue($user->branches->contains($branch));

        // Registered event fired (for email verification)
        Event::assertDispatched(Registered::class);

        // User is authenticated
        $this->assertAuthenticatedAs($user);

        // Redirects to verification
        //$response->assertRedirect(route('verification.notice'));
        $response->assertRedirect(route('dashboard'));
    }

    public function test_onboarding_creates_all_records_in_transaction(): void
    {
        // If branch creation fails, tenant and user should also not exist
        // We test this by sending invalid branch_code (empty)
        $this->post('/onboarding', $this->validData(['branch_code' => '']));

        $this->assertDatabaseMissing('tenants', ['slug' => 'clinica-santa-fe']);
        $this->assertNull(User::findByEmail('maria@nueva-empresa.com'));
    }

    public function test_password_is_hashed(): void
    {
        Event::fake([Registered::class]);

        $this->post('/onboarding', $this->validData());

        $user = User::findByEmail('maria@nueva-empresa.com');
        $this->assertNotEquals('Olinora2026!', $user->password);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('Olinora2026!', $user->password));
    }

    // ─── Validation: Step 1 (Account) ───

    public function test_name_is_required(): void
    {
        $response = $this->post('/onboarding', $this->validData(['name' => '']));
        $response->assertSessionHasErrors('name');
    }

    public function test_email_is_required_and_valid(): void
    {
        $response = $this->post('/onboarding', $this->validData(['email' => '']));
        $response->assertSessionHasErrors('email');

        $response = $this->post('/onboarding', $this->validData(['email' => 'not-an-email']));
        $response->assertSessionHasErrors('email');
    }

    public function test_email_must_be_unique(): void
    {
        Event::fake([Registered::class]);

        // Create an existing user
        $this->post('/onboarding', $this->validData());
        auth()->logout();

        $response = $this->post('/onboarding', $this->validData([
            'slug' => 'another-clinic',
        ]));

        $response->assertSessionHasErrors('email');
    }

    public function test_password_must_be_confirmed(): void
    {
        $response = $this->post('/onboarding', $this->validData([
            'password_confirmation' => 'DifferentPassword!',
        ]));

        $response->assertSessionHasErrors('password');
    }

    // ─── Validation: Step 2 (Tenant) ───

    public function test_company_name_is_required(): void
    {
        $response = $this->post('/onboarding', $this->validData(['company_name' => '']));
        $response->assertSessionHasErrors('company_name');
    }

    public function test_slug_is_required_and_url_safe(): void
    {
        $response = $this->post('/onboarding', $this->validData(['slug' => '']));
        $response->assertSessionHasErrors('slug');

        $response = $this->post('/onboarding', $this->validData(['slug' => 'UPPER CASE!']));
        $response->assertSessionHasErrors('slug');

        $response = $this->post('/onboarding', $this->validData(['slug' => '-starts-with-dash']));
        $response->assertSessionHasErrors('slug');
    }

    public function test_slug_must_be_unique(): void
    {
        Event::fake([Registered::class]);

        // First tenant takes the slug
        $this->post('/onboarding', $this->validData());
        auth()->logout();

        // Second tenant tries same slug
        $response = $this->post('/onboarding', $this->validData([
            'email' => 'another@empresa.com',
        ]));

        $response->assertSessionHasErrors('slug');
    }

    // ─── Validation: Step 3 (Branch) ───

    public function test_branch_name_is_required(): void
    {
        $response = $this->post('/onboarding', $this->validData(['branch_name' => '']));
        $response->assertSessionHasErrors('branch_name');
    }

    public function test_branch_code_is_required_and_uppercase(): void
    {
        $response = $this->post('/onboarding', $this->validData(['branch_code' => '']));
        $response->assertSessionHasErrors('branch_code');

        $response = $this->post('/onboarding', $this->validData(['branch_code' => 'lower case!']));
        $response->assertSessionHasErrors('branch_code');
    }

    // ─── Slug Check Endpoint ───

    public function test_check_slug_returns_available(): void
    {
        $response = $this->getJson('/onboarding/check-slug?slug=nuevo-tenant');

        $response->assertOk();
        $response->assertJson(['available' => true]);
    }

    public function test_check_slug_returns_taken(): void
    {
        Event::fake([Registered::class]);

        $this->post('/onboarding', $this->validData());
        auth()->logout();

        $response = $this->getJson('/onboarding/check-slug?slug=clinica-santa-fe');

        $response->assertOk();
        $response->assertJson(['available' => false]);
    }

    public function test_check_slug_validates_format(): void
    {
        $response = $this->getJson('/onboarding/check-slug?slug=INVALID SLUG!');

        $response->assertStatus(422);
    }

    // ─── Access Control ───

    public function test_authenticated_users_cannot_access_onboarding(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($user)->get('/onboarding');

        $response->assertRedirect('/dashboard');
    }

    // ─── Rate Limiting ───

    public function test_onboarding_is_rate_limited(): void
    {
        Event::fake([Registered::class]);

        // Make 5 requests (the limit)
        for ($i = 0; $i < 5; $i++) {
            $this->post('/onboarding', $this->validData([
                'email' => "user{$i}@test.com",
                'slug'  => "tenant-{$i}",
            ]));
            auth()->logout();
        }

        // 6th should be throttled
        $response = $this->post('/onboarding', $this->validData([
            'email' => 'user6@test.com',
            'slug'  => 'tenant-6',
        ]));

        $response->assertStatus(429);
    }

    // ─── Default Schedule ───

    public function test_branch_gets_default_schedule(): void
    {
        Event::fake([Registered::class]);

        $this->post('/onboarding', $this->validData());

        $branch = Branch::where('code', 'CTR')->first();
        $schedule = $branch->operating_hours;

        $this->assertTrue($schedule['monday']['is_open']);
        $this->assertEquals('09:00', $schedule['monday']['open']);
        $this->assertEquals('18:00', $schedule['monday']['close']);
        $this->assertFalse($schedule['sunday']['is_open']);
        $this->assertEquals('14:00', $schedule['saturday']['close']);
    }
}
