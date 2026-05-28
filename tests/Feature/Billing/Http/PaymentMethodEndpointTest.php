<?php

declare(strict_types=1);

namespace Tests\Feature\Billing\Http;

use App\Billing\Contracts\BillingGatewayWriter;
use App\Billing\DTOs\GatewayPaymentMethod;
use App\Billing\DTOs\GatewaySetupIntent;
use App\Enums\Billing\Gateway;
use App\Models\Billing\Customer;
use App\Models\Billing\CustomerGatewayRef;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for PaymentMethodController (PR-AA).
 *
 * Stack: HTTP + middleware (auth + verified + tenant.scope +
 * EnsureTwoFactorForAdmins + role:admin) + controller + Actions + DB.
 * Only the BillingGatewayWriter is mocked.
 */
final class PaymentMethodEndpointTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Stub Stripe credentials so StripeClientFactory's eager validation
     * passes during container resolution. The controller's action
     * dependencies (CreateSetupIntentAction, UpdatePaymentMethodAction)
     * inject BillingGatewayWriter, which the provider binds to
     * StripeBillingGateway → StripeClientFactory::make(). Without these
     * stubs, every test in this file 500s during controller resolution
     * (before assertions run) in environments without Stripe env vars
     * (CI default). Stub values are never used for real API calls — the
     * BillingGatewayWriter is mocked per-test (see class docblock). This
     * mirrors the pattern in BillingContainerBindingsTest::setUp().
     */
    protected function setUp(): void
    {
        parent::setUp();

        Config::set(
            'billing.gateways.stripe.secret_key',
            'sk_test_stub_for_container_audit_only'
        );
        Config::set(
            'billing.gateways.stripe.public_key',
            'pk_test_stub_for_container_audit_only'
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function setupTenantWithCustomer(): array
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create();

        /** @var User $admin */
        $admin = User::factory()->admin()->create([
            'tenant_id' => $tenant->id,
        ]);

        /** @var Customer $customer */
        $customer = Customer::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        CustomerGatewayRef::create([
            'customer_id' => $customer->id,
            'gateway' => Gateway::Stripe->value,
            'gateway_customer_id' => 'cus_TEST_LINKED',
            'metadata' => null,
        ]);

        return [
            'tenant' => $tenant,
            'admin' => $admin,
            'customer' => $customer,
        ];
    }

    private function bindWriterMock(MockInterface $writer): void
    {
        $this->app->instance(BillingGatewayWriter::class, $writer);
    }

    #[Test]
    public function show_returns_200_with_setup_intent_client_secret_for_authenticated_admin(): void
    {
        $ctx = $this->setupTenantWithCustomer();

        /** @var BillingGatewayWriter&MockInterface $writer */
        $writer = Mockery::mock(BillingGatewayWriter::class);
        $writer->shouldReceive('createSetupIntent')
            ->once()
            ->andReturn(new GatewaySetupIntent(
                gatewayId: 'seti_HAPPY',
                clientSecret: 'seti_HAPPY_secret_xyz',
                status: 'requires_payment_method',
            ));
        $this->bindWriterMock($writer);

        $this->actingAs($ctx['admin'])
            ->get(route('admin.payment-method.show'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Billing/PaymentMethod/Index')
                ->where('setupIntentClientSecret', 'seti_HAPPY_secret_xyz')
                ->has('stripePublicKey')
            );
    }

    #[Test]
    public function show_returns_404_when_tenant_has_no_billing_customer(): void
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create();
        /** @var User $admin */
        $admin = User::factory()->admin()->create([
            'tenant_id' => $tenant->id,
        ]);
        // No Customer created.

        $this->actingAs($admin)
            ->get(route('admin.payment-method.show'))
            ->assertNotFound();
    }

    #[Test]
    public function show_redirects_unauthenticated_visitor(): void
    {
        $this->get(route('admin.payment-method.show'))
            ->assertRedirect(route('login'));
    }

    #[Test]
    public function show_forbids_operator_role(): void
    {
        $ctx = $this->setupTenantWithCustomer();

        /** @var User $operator */
        $operator = User::factory()->operator()->create([
            'tenant_id' => $ctx['tenant']->id,
        ]);

        $this->actingAs($operator)
            ->get(route('admin.payment-method.show'))
            ->assertForbidden();
    }

    #[Test]
    public function store_attaches_payment_method_and_redirects_with_success(): void
    {
        $ctx = $this->setupTenantWithCustomer();

        /** @var BillingGatewayWriter&MockInterface $writer */
        $writer = Mockery::mock(BillingGatewayWriter::class);
        $writer->shouldReceive('attachPaymentMethod')
            ->once()
            ->andReturn(new GatewayPaymentMethod(
                gatewayId: 'pm_NEW',
                gatewayCustomerId: 'cus_TEST_LINKED',
                type: 'card',
                brand: 'visa',
                last4: '4242',
                expMonth: 12,
                expYear: 2030,
                isDefault: true,
                metadata: [],
            ));
        $this->bindWriterMock($writer);

        $this->actingAs($ctx['admin'])
            ->post(route('admin.payment-method.store'), [
                'payment_method_id' => 'pm_NEW123456',
                'set_as_default' => true,
            ])
            ->assertRedirect(route('admin.payment-method.show'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('billing_payment_methods', [
            'customer_id' => $ctx['customer']->id,
            'stripe_payment_method_id' => 'pm_NEW',
            'brand' => 'visa',
            'last4' => '4242',
            'is_default' => true,
        ]);
    }

    #[Test]
    public function store_validates_payment_method_id_format(): void
    {
        $ctx = $this->setupTenantWithCustomer();

        /** @var BillingGatewayWriter&MockInterface $writer */
        $writer = Mockery::mock(BillingGatewayWriter::class);
        $writer->shouldNotReceive('attachPaymentMethod');
        $this->bindWriterMock($writer);

        $this->actingAs($ctx['admin'])
            ->from(route('admin.payment-method.show'))
            ->post(route('admin.payment-method.store'), [
                'payment_method_id' => 'invalid_format',
            ])
            ->assertSessionHasErrors('payment_method_id');
    }

    #[Test]
    public function store_forbids_operator_role(): void
    {
        $ctx = $this->setupTenantWithCustomer();

        /** @var User $operator */
        $operator = User::factory()->operator()->create([
            'tenant_id' => $ctx['tenant']->id,
        ]);

        $this->actingAs($operator)
            ->post(route('admin.payment-method.store'), [
                'payment_method_id' => 'pm_anyvalue123',
            ])
            ->assertForbidden();
    }
}
