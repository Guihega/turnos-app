<?php

declare(strict_types=1);

namespace Tests\Unit\Billing\Jobs;

use App\Jobs\Billing\ProcessBillingWebhookEvent;
use App\Models\Billing\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Unit tests for ProcessBillingWebhookEvent (PR-F scope: skeleton).
 *
 * PR-G will extend the job with per-event-type handlers. The
 * invariants verified here are independent of those handlers:
 *
 *   - processed_at is stamped on success
 *   - attempts is incremented
 *   - already-processed events are no-ops
 *   - missing rows are no-ops with a log entry
 *   - failed() marks needs_review and stores last_error
 */
final class ProcessBillingWebhookEventTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeEvent(array $overrides = []): WebhookEvent
    {
        /** @var WebhookEvent $event */
        $event = WebhookEvent::create(array_merge([
            'gateway' => 'stripe',
            'gateway_event_id' => 'evt_'.uniqid(),
            'event_type' => 'customer.subscription.updated',
            'payload' => ['id' => 'evt_x', 'type' => 'customer.subscription.updated'],
            'signature_header' => 't=1700000000,v1=dummy',
        ], $overrides));

        return $event;
    }

    #[Test]
    public function handle_marks_processed_and_increments_attempts(): void
    {
        $event = $this->makeEvent();
        $this->assertNull($event->processed_at);
        $this->assertSame(0, $event->attempts);

        $job = new ProcessBillingWebhookEvent($event->id);
        $job->handle();

        $event->refresh();
        $this->assertNotNull($event->processed_at);
        $this->assertSame(1, $event->attempts);
        $this->assertNull($event->last_error);
    }

    #[Test]
    public function handle_is_noop_when_already_processed(): void
    {
        $event = $this->makeEvent(['processed_at' => now(), 'attempts' => 5]);

        $job = new ProcessBillingWebhookEvent($event->id);
        $job->handle();

        $event->refresh();
        // attempts NOT incremented; the early-return short-circuited.
        $this->assertSame(5, $event->attempts);
    }

    #[Test]
    public function handle_is_noop_when_webhook_event_id_does_not_exist(): void
    {
        $job = new ProcessBillingWebhookEvent('01HXXXXXXXXXXXXXXXXXXXXXXX');

        // Should NOT throw; just logs and returns.
        $job->handle();

        $this->assertSame(0, WebhookEvent::count());
    }

    #[Test]
    public function failed_marks_needs_review_and_stores_last_error(): void
    {
        $event = $this->makeEvent();
        $job = new ProcessBillingWebhookEvent($event->id);

        $exception = new RuntimeException('Simulated processing failure');
        $job->failed($exception);

        $event->refresh();
        $this->assertTrue($event->needs_review);
        $this->assertSame('Simulated processing failure', $event->last_error);
    }
}
