<?php

declare(strict_types=1);

namespace Tests\Feature\Billing\Jobs;

use App\Jobs\Billing\PurgeOutboxEventsJob;
use App\Models\Billing\BillingOutboxEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PurgeOutboxEventsJobTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_deletes_published_rows_older_than_retention(): void
    {
        $old = $this->makeRow();
        $old->published_at = Carbon::now()->subDays(31);
        $old->save();

        $young = $this->makeRow();
        $young->published_at = Carbon::now()->subDays(29);
        $young->save();

        $this->runJob();

        $this->assertDatabaseMissing('billing_outbox_events', ['id' => $old->id]);
        $this->assertDatabaseHas('billing_outbox_events', ['id' => $young->id]);
    }

    #[Test]
    public function it_keeps_pending_rows_regardless_of_age(): void
    {
        $row = $this->makeRow();
        $row->created_at = Carbon::now()->subDays(60);
        $row->save();

        $this->runJob();

        $this->assertDatabaseHas('billing_outbox_events', ['id' => $row->id]);
    }

    #[Test]
    public function it_never_purges_failed_rows(): void
    {
        $row = $this->makeRow();
        $row->published_at = Carbon::now()->subDays(60);
        $row->failed_at = Carbon::now()->subDays(50);
        $row->attempts = 3;
        $row->save();

        $this->runJob();

        $this->assertDatabaseHas('billing_outbox_events', ['id' => $row->id]);
    }

    #[Test]
    public function it_respects_the_configured_retention_window(): void
    {
        config()->set('billing.outbox.retention_days', 7);

        $just_over = $this->makeRow();
        $just_over->published_at = Carbon::now()->subDays(8);
        $just_over->save();

        $just_under = $this->makeRow();
        $just_under->published_at = Carbon::now()->subDays(6);
        $just_under->save();

        $this->runJob();

        $this->assertDatabaseMissing('billing_outbox_events', ['id' => $just_over->id]);
        $this->assertDatabaseHas('billing_outbox_events', ['id' => $just_under->id]);
    }

    private function runJob(): void
    {
        (new PurgeOutboxEventsJob)->handle();
    }

    private function makeRow(): BillingOutboxEvent
    {
        return BillingOutboxEvent::create([
            'aggregate_type' => 'App\\Models\\Billing\\Subscription',
            'aggregate_id' => '01J0000000000000000000ABCD',
            'event_type' => 'subscription.state-changed',
            'payload' => ['k' => 'v'],
        ]);
    }
}
