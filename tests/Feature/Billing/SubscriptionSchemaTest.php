<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\Billing\InvoiceStatus;
use App\Enums\Billing\PaymentMethodType;
use App\Enums\Billing\PaymentStatus;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Billing\Customer;
use App\Models\Billing\Invoice;
use App\Models\Billing\InvoiceLine;
use App\Models\Billing\Payment;
use App\Models\Billing\PaymentMethod;
use App\Models\Billing\Plan;
use App\Models\Billing\Price;
use App\Models\Billing\Subscription;
use App\Models\Billing\SubscriptionItem;
use App\Models\Billing\SubscriptionStateTransition;
use Database\Seeders\Billing\BillingCatalogSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Schema-level tests for Phase 2 PR-A.
 *
 * These tests exercise the migrations and model wiring directly,
 * not application logic. They verify:
 *   - FK integrity
 *   - Soft delete behavior where applicable
 *   - The unique partial index that limits one active subscription per customer
 *   - The PostgreSQL trigger that makes state transitions append-only
 *   - The idempotency_key UNIQUE constraint on payments
 *   - Cascade behavior on invoice → invoice_lines
 */
final class SubscriptionSchemaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BillingCatalogSeeder::class);
    }

    #[Test]
    public function a_subscription_belongs_to_customer_and_plan(): void
    {
        $customer = Customer::factory()->create();
        $plan = Plan::where('code', 'pilot')->firstOrFail();
        $this->assertInstanceOf(Plan::class, $plan);

        $subscription = Subscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Pilot,
            'trial_ends_at' => now()->addDays(90),
        ]);

        // Reload with relations and narrow types via assertInstanceOf so
        // Larastan can resolve $relation->id without falling back to Model.
        $loaded = Subscription::with(['customer', 'plan'])->findOrFail($subscription->id);
        $this->assertInstanceOf(Subscription::class, $loaded);
        $this->assertInstanceOf(Customer::class, $loaded->customer);
        $this->assertInstanceOf(Plan::class, $loaded->plan);

        $this->assertSame($customer->id, $loaded->customer->id);
        $this->assertSame($plan->id, $loaded->plan->id);
        $this->assertNull($loaded->price_id);
    }

    #[Test]
    public function a_customer_cannot_have_two_active_subscriptions_simultaneously(): void
    {
        $customer = Customer::factory()->create();
        $plan = Plan::where('code', 'pilot')->firstOrFail();

        Subscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
        ]);

        $this->expectException(QueryException::class);

        // Same customer, second active subscription must violate the partial unique index.
        Subscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
        ]);
    }

    #[Test]
    public function a_customer_can_have_a_canceled_and_a_new_active_subscription(): void
    {
        $customer = Customer::factory()->create();
        $plan = Plan::where('code', 'pilot')->firstOrFail();

        Subscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Canceled,
            'canceled_at' => now()->subDays(30),
        ]);

        // Should succeed: 'canceled' is not in the partial-index status set.
        $newSub = Subscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
        ]);

        $this->assertCount(2, Subscription::where('customer_id', $customer->id)->get());
        $this->assertSame(SubscriptionStatus::Active, $newSub->status);
    }

    #[Test]
    public function state_transitions_table_rejects_updates_via_postgres_trigger(): void
    {
        $customer = Customer::factory()->create();
        $plan = Plan::where('code', 'pilot')->firstOrFail();
        $subscription = Subscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Pilot,
        ]);

        $transition = SubscriptionStateTransition::create([
            'subscription_id' => $subscription->id,
            'from_status' => SubscriptionStatus::Pilot->value,
            'to_status' => SubscriptionStatus::Active->value,
            'reason' => 'user_upgrade',
            'transitioned_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/append-only|not permitted/i');

        // Direct DB update bypassing Eloquent — must be rejected by the trigger.
        DB::table('billing_subscription_state_transitions')
            ->where('id', $transition->id)
            ->update(['reason' => 'tampered']);
    }

    #[Test]
    public function state_transitions_table_rejects_deletes_via_postgres_trigger(): void
    {
        $customer = Customer::factory()->create();
        $plan = Plan::where('code', 'pilot')->firstOrFail();
        $subscription = Subscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Pilot,
        ]);

        $transition = SubscriptionStateTransition::create([
            'subscription_id' => $subscription->id,
            'from_status' => SubscriptionStatus::Pilot->value,
            'to_status' => SubscriptionStatus::Active->value,
            'reason' => 'user_upgrade',
            'transitioned_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/append-only|not permitted/i');

        DB::table('billing_subscription_state_transitions')
            ->where('id', $transition->id)
            ->delete();
    }

    #[Test]
    public function deleting_an_invoice_cascades_to_invoice_lines(): void
    {
        $customer = Customer::factory()->create();
        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'status' => InvoiceStatus::Open,
            'invoice_number' => 'INV-TEST-'.Str::upper(Str::random(8)),
            'currency' => 'USD',
            'subtotal_cents' => 2900,
            'tax_cents' => 0,
            'total_cents' => 2900,
            'amount_paid_cents' => 0,
            'amount_due_cents' => 2900,
            'issued_at' => Carbon::today(),
        ]);

        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'description' => 'Starter plan, monthly',
            'quantity' => 1,
            'unit_amount_cents' => 2900,
            'amount_cents' => 2900,
        ]);

        $this->assertSame(1, InvoiceLine::where('invoice_id', $invoice->id)->count());

        // Force delete to trigger cascade (the model has no softDeletes).
        $invoice->delete();

        $this->assertSame(0, InvoiceLine::where('invoice_id', $invoice->id)->count());
    }

    #[Test]
    public function payments_require_a_unique_idempotency_key(): void
    {
        $customer = Customer::factory()->create();
        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'status' => InvoiceStatus::Open,
            'invoice_number' => 'INV-TEST-'.Str::upper(Str::random(8)),
            'currency' => 'USD',
            'subtotal_cents' => 2900,
            'tax_cents' => 0,
            'total_cents' => 2900,
            'amount_paid_cents' => 0,
            'amount_due_cents' => 2900,
            'issued_at' => Carbon::today(),
        ]);

        $idempotencyKey = 'pay_'.Str::ulid();

        Payment::create([
            'invoice_id' => $invoice->id,
            'status' => PaymentStatus::Succeeded,
            'currency' => 'USD',
            'amount_cents' => 2900,
            'idempotency_key' => $idempotencyKey,
            'processed_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        // Second payment with the same idempotency_key must fail.
        Payment::create([
            'invoice_id' => $invoice->id,
            'status' => PaymentStatus::Succeeded,
            'currency' => 'USD',
            'amount_cents' => 2900,
            'idempotency_key' => $idempotencyKey,
            'processed_at' => now(),
        ]);
    }

    #[Test]
    public function payment_method_uses_enum_cast(): void
    {
        $customer = Customer::factory()->create();

        $pm = PaymentMethod::create([
            'customer_id' => $customer->id,
            'type' => PaymentMethodType::Card,
            'is_default' => true,
            'brand' => 'visa',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2030,
        ]);

        $this->assertInstanceOf(PaymentMethodType::class, $pm->type);
        $this->assertSame(PaymentMethodType::Card, $pm->type);
        $this->assertTrue($pm->is_default);
    }

    #[Test]
    public function subscription_item_kind_defaults_to_subscription(): void
    {
        $customer = Customer::factory()->create();
        $plan = Plan::where('code', 'starter')->firstOrFail();
        $this->assertInstanceOf(Plan::class, $plan);

        $price = $plan->prices()->where('currency', 'USD')->firstOrFail();
        $this->assertInstanceOf(Price::class, $price);

        $subscription = Subscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'price_id' => $price->id,
            'status' => SubscriptionStatus::Active,
        ]);

        $item = SubscriptionItem::create([
            'subscription_id' => $subscription->id,
            'price_id' => $price->id,
            // 'kind' omitted — should default to 'subscription'.
        ]);

        $this->assertSame(SubscriptionItem::KIND_SUBSCRIPTION, $item->kind);
        $this->assertSame(1, $item->quantity);
    }

    #[Test]
    public function active_statuses_helper_matches_partial_index_definition(): void
    {
        // The Subscription::activeStatuses() PHP definition must match
        // the WHERE clause of the partial unique index in the migration.
        $expected = [
            SubscriptionStatus::Pilot,
            SubscriptionStatus::Trialing,
            SubscriptionStatus::Active,
            SubscriptionStatus::PastDue,
            SubscriptionStatus::Paused,
        ];

        $this->assertSame($expected, Subscription::activeStatuses());
    }
}
