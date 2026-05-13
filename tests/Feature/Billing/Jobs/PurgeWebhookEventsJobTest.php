<?php

declare(strict_types=1);

namespace Tests\Feature\Billing\Jobs;

use App\Jobs\Billing\PurgeWebhookEventsJob;
use App\Models\Billing\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PurgeWebhookEventsJobTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_deletes_processed_rows_older_than_retention(): void
    {
        $old = $this->makeRow('evt_old');
        $old->processed_at = Carbon::now()->subDays(91);
        $old->save();

        $young = $this->makeRow('evt_young');
        $young->processed_at = Carbon::now()->subDays(89);
        $young->save();

        $this->runJob();

        $this->assertDatabaseMissing('billing_webhook_events', ['id' => $old->id]);
        $this->assertDatabaseHas('billing_webhook_events', ['id' => $young->id]);
    }

    #[Test]
    public function it_keeps_unprocessed_rows_regardless_of_age(): void
    {
        $row = $this->makeRow('evt_pending');
        $row->created_at = Carbon::now()->subDays(180);
        $row->save();

        $this->runJob();

        $this->assertDatabaseHas('billing_webhook_events', ['id' => $row->id]);
    }

    #[Test]
    public function it_never_purges_rows_flagged_for_review(): void
    {
        $row = $this->makeRow('evt_needs_review');
        $row->processed_at = Carbon::now()->subDays(120);
        $row->needs_review = true;
        $row->save();

        $this->runJob();

        $this->assertDatabaseHas('billing_webhook_events', ['id' => $row->id]);
    }

    #[Test]
    public function it_respects_the_configured_retention_window(): void
    {
        config()->set('billing.webhooks.retention_days', 30);

        $just_over = $this->makeRow('evt_just_over');
        $just_over->processed_at = Carbon::now()->subDays(31);
        $just_over->save();

        $just_under = $this->makeRow('evt_just_under');
        $just_under->processed_at = Carbon::now()->subDays(29);
        $just_under->save();

        $this->runJob();

        $this->assertDatabaseMissing('billing_webhook_events', ['id' => $just_over->id]);
        $this->assertDatabaseHas('billing_webhook_events', ['id' => $just_under->id]);
    }

    private function runJob(): void
    {
        (new PurgeWebhookEventsJob)->handle();
    }

    private function makeRow(string $gatewayEventId): WebhookEvent
    {
        return WebhookEvent::create([
            'gateway' => 'stripe',
            'gateway_event_id' => $gatewayEventId,
            'event_type' => 'customer.subscription.updated',
            'payload' => ['data' => ['object' => ['id' => 'sub_test']]],
        ]);
    }
}
