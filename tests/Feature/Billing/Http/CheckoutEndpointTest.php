<?php

declare(strict_types=1);

namespace Tests\Feature\Billing\Http;

use App\Billing\Contracts\BillingGatewayWriter;
use App\Billing\DTOs\GatewayCustomer;
use App\Billing\DTOs\GatewaySubscription;
use App\Models\Billing\Plan;
use App\Models\Billing\Price;
use App\Models\Tenant;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Feature tests for /checkout/* endpoints (PR-O).
 *
 * Full HTTP → Controller → FormRequest → Action → DB chain.
 * Only the BillingGatewayWriter is mocked (no real Stripe calls).
 */
final class CheckoutEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function bindGatewayMock(): void
    {
        /** @var BillingGatewayWriter&MockInterface $writer */
        $writer = Mockery::mock(BillingGatewayWriter::class);

        $writer->shouldReceive('createCustomer')
            ->andReturn(new GatewayCustomer(
                gatewayId: 'cus_TEST',
                email: 'test@example.com',
                name: 'Test Co',
                defaultPaymentMethodId: null,
                currency: 'mxn',
                deleted: false,
                metadata: [],
            ));

        $writer->shouldReceive('createSubscription')
            ->andReturn(new GatewaySubscription(
                gatewayId: 'sub_TEST',
                gatewayCustomerId: 'cus_TEST',
                status: 'trialing',
                rawStatus: 'trialing',
                items: [],
                currentPeriodStart: new DateTimeImmutable('2026-05-14'),
                currentPeriodEnd: new DateTimeImmutable('2026-06-14'),
                trialEnd: new DateTimeImmutable('2026-05-28'),
                cancelAt: null,
                canceledAt: null,
                cancelAtPeriodEnd: false,
                metadata: [],
            ));

        $this->app->instance(BillingGatewayWriter::class, $writer);
    }

    private function publicPlanWithMxnPrices(string $code = 'starter'): Plan
    {
        /** @var Plan $plan */
        $plan = Plan::factory()->create([
            'code' => $code,
            'name' => 'Starter',
            'is_public' => true,
            'is_active' => true,
        ]);

        Price::factory()->for($plan)->inMxn()->monthly()->create([
            'amount_cents' => 49900,
            'gateway_refs' => ['stripe' => 'price_starter_mxn_monthly'],
        ]);

        Price::factory()->for($plan)->inMxn()->yearly()->create([
            'amount_cents' => 499000,
            'gateway_refs' => ['stripe' => 'price_starter_mxn_yearly'],
        ]);

        return $plan;
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(string $planCode = 'starter', string $interval = 'month'): array
    {
        return [
            'plan_code' => $planCode,
            'interval' => $interval,
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
            'company_name' => 'Lovelace Studios',
            'slug' => 'lovelace-studios',
            'branch_name' => 'Centro',
            'branch_code' => 'CENTRO',
        ];
    }

    // ─── select() ──────────────────────────────────────────────────────

    public function test_select_renders_with_public_active_plans(): void
    {
        $this->publicPlanWithMxnPrices('starter');
        $this->publicPlanWithMxnPrices('professional');

        $response = $this->get('/checkout');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Billing/Checkout/Select')
            ->has('plans', 2)
        );
    }

    public function test_select_excludes_private_plans(): void
    {
        $this->publicPlanWithMxnPrices('starter');
        Plan::factory()->private()->create(['code' => 'enterprise']);

        $response = $this->get('/checkout');

        $response->assertInertia(fn ($page) => $page->has('plans', 1));
    }

    public function test_select_excludes_inactive_plans(): void
    {
        $this->publicPlanWithMxnPrices('starter');
        Plan::factory()->inactive()->create(['code' => 'legacy']);

        $response = $this->get('/checkout');

        $response->assertInertia(fn ($page) => $page->has('plans', 1));
    }

    // ─── signupForm() ──────────────────────────────────────────────────

    public function test_signup_form_renders_with_valid_plan_and_interval(): void
    {
        $this->publicPlanWithMxnPrices('starter');

        $response = $this->get('/checkout/signup?plan=starter&interval=month');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Billing/Checkout/Signup')
            ->where('plan.code', 'starter')
            ->where('price.interval', 'month')
            ->where('price.currency', 'MXN')
        );
    }

    public function test_signup_form_redirects_when_plan_invalid(): void
    {
        $response = $this->get('/checkout/signup?plan=nonexistent&interval=month');

        $response->assertRedirect(route('checkout.select'));
    }

    public function test_signup_form_redirects_when_interval_invalid(): void
    {
        $this->publicPlanWithMxnPrices('starter');

        $response = $this->get('/checkout/signup?plan=starter&interval=weekly');

        $response->assertRedirect(route('checkout.select'));
    }

    // ─── signup() ──────────────────────────────────────────────────────

    public function test_signup_creates_tenant_user_branch_customer_subscription(): void
    {
        Event::fake([Registered::class]);
        $this->publicPlanWithMxnPrices('starter');
        $this->bindGatewayMock();

        $response = $this->post('/checkout', $this->validPayload());

        $response->assertRedirect(route('checkout.confirmation'));

        $this->assertDatabaseHas('tenants', [
            'slug' => 'lovelace-studios',
            'name' => 'Lovelace Studios',
        ]);

        $tenant = Tenant::where('slug', 'lovelace-studios')->firstOrFail();

        $user = User::findByEmail('ada@example.com');
        $this->assertNotNull($user);
        $this->assertSame($tenant->id, $user->tenant_id);

        $this->assertDatabaseHas('branches', [
            'tenant_id' => $tenant->id,
            'code' => 'CENTRO',
        ]);

        $this->assertDatabaseHas('billing_customers', [
            'tenant_id' => $tenant->id,
        ]);

        $this->assertDatabaseHas('billing_subscriptions', [
            'status' => 'trialing',
            'stripe_subscription_id' => 'sub_TEST',
        ]);

        Event::assertDispatched(Registered::class);
        $this->assertAuthenticatedAs($user);
    }

    public function test_signup_redirects_to_confirmation_with_flash_data(): void
    {
        $this->publicPlanWithMxnPrices('starter');
        $this->bindGatewayMock();

        $response = $this->post('/checkout', $this->validPayload());

        $response->assertRedirect(route('checkout.confirmation'));
        $response->assertSessionHas('checkout');
        $flash = session('checkout');
        $this->assertIsArray($flash);
        $this->assertSame('lovelace-studios', $flash['tenant_slug']);
        $this->assertSame('Starter', $flash['plan_name']);
        $this->assertSame('month', $flash['interval']);
    }

    public function test_signup_validates_required_fields(): void
    {
        $response = $this->post('/checkout', []);

        $response->assertSessionHasErrors([
            'plan_code', 'interval', 'name', 'email', 'password',
            'company_name', 'slug', 'branch_name', 'branch_code',
        ]);
    }

    public function test_signup_rejects_invalid_plan_code(): void
    {
        $this->publicPlanWithMxnPrices('starter');

        $payload = $this->validPayload();
        $payload['plan_code'] = 'nonexistent';

        $response = $this->post('/checkout', $payload);

        $response->assertSessionHasErrors('plan_code');
    }

    public function test_signup_rejects_duplicate_slug(): void
    {
        $this->publicPlanWithMxnPrices('starter');
        Tenant::factory()->create(['slug' => 'lovelace-studios']);

        $response = $this->post('/checkout', $this->validPayload());

        $response->assertSessionHasErrors('slug');
        $this->assertGuest();
    }

    // ─── confirmation() ────────────────────────────────────────────────

    public function test_confirmation_renders_when_flash_data_present(): void
    {
        $response = $this->withSession(['checkout' => [
            'tenant_name' => 'Lovelace Studios',
            'tenant_slug' => 'lovelace-studios',
            'plan_name' => 'Starter',
            'interval' => 'month',
            'trial_ends_at' => '2026-05-28T00:00:00+00:00',
        ]])->get('/checkout/confirmation');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Billing/Checkout/Confirmation')
            ->where('tenantSlug', 'lovelace-studios')
            ->where('planName', 'Starter')
        );
    }

    public function test_confirmation_redirects_to_dashboard_when_no_flash(): void
    {
        $response = $this->get('/checkout/confirmation');

        $response->assertRedirect(route('dashboard'));
    }
}
