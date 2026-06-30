<?php

declare(strict_types=1);

namespace Tests\Unit\Billing\Webhooks\Handlers;

use App\Billing\Webhooks\Handlers\InvoicePaidHandler;
use App\Enums\Billing\InvoiceStatus;
use App\Models\Billing\Invoice;
use App\Models\Billing\Subscription;
use App\Models\Billing\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class InvoicePaidHandlerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeEvent(string $stripeSubId, array $overrides = []): WebhookEvent
    {
        $object = array_merge([
            'id' => 'in_TEST123',
            'subscription' => $stripeSubId,
            'number' => 'OLI-0001',
            'currency' => 'mxn',
            'subtotal' => 49900,
            'tax' => 0,
            'total' => 49900,
            'amount_paid' => 49900,
            'amount_due' => 0,
            'status_transitions' => ['paid_at' => 1751299200],
        ], $overrides);

        /** @var WebhookEvent $event */
        $event = WebhookEvent::create([
            'gateway' => 'stripe',
            'gateway_event_id' => 'evt_'.uniqid(),
            'event_type' => 'invoice.paid',
            'payload' => [
                'id' => 'evt_x',
                'type' => 'invoice.paid',
                'data' => ['object' => $object],
            ],
            'signature_header' => 't=1,v1=dummy',
        ]);

        return $event;
    }

    #[Test]
    public function persists_a_paid_invoice_from_the_event(): void
    {
        /** @var Subscription $sub */
        $sub = Subscription::factory()
            ->active()
            ->withStripeId('sub_paid_ok')
            ->create();

        $event = $this->makeEvent('sub_paid_ok');

        $handler = $this->app->make(InvoicePaidHandler::class);
        $handler->handle($event);

        $this->assertDatabaseHas('billing_invoices', [
            'stripe_invoice_id' => 'in_TEST123',
            'subscription_id' => $sub->id,
            'customer_id' => $sub->customer_id,
            'status' => InvoiceStatus::Paid->value,
            'total_cents' => 49900,
            'amount_paid_cents' => 49900,
        ]);
    }

    #[Test]
    public function is_idempotent_on_duplicate_delivery(): void
    {
        /** @var Subscription $sub */
        $sub = Subscription::factory()
            ->active()
            ->withStripeId('sub_paid_dup')
            ->create();

        $handler = $this->app->make(InvoicePaidHandler::class);

        $handler->handle($this->makeEvent('sub_paid_dup'));
        $handler->handle($this->makeEvent('sub_paid_dup'));

        $this->assertSame(
            1,
            Invoice::query()->where('stripe_invoice_id', 'in_TEST123')->count(),
        );
    }

    #[Test]
    public function skips_when_subscription_not_owned(): void
    {
        $event = $this->makeEvent('sub_unknown_not_in_db');

        $handler = $this->app->make(InvoicePaidHandler::class);
        $handler->handle($event);

        $this->assertDatabaseMissing('billing_invoices', [
            'stripe_invoice_id' => 'in_TEST123',
        ]);
    }

    #[Test]
    public function skips_one_off_invoice_without_subscription(): void
    {
        $event = $this->makeEvent('sub_ignored', ['subscription' => null]);

        $handler = $this->app->make(InvoicePaidHandler::class);
        $handler->handle($event);

        $this->assertDatabaseMissing('billing_invoices', [
            'stripe_invoice_id' => 'in_TEST123',
        ]);
    }
}
