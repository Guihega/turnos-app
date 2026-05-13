<?php

declare(strict_types=1);

namespace Tests\Feature\Billing\Jobs;

use App\Contracts\Billing\OutboxEventHandler;
use App\Jobs\Billing\PublishOutboxEventsJob;
use App\Models\Billing\BillingOutboxEvent;
use App\Services\Billing\OutboxEventDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

final class PublishOutboxEventsJobTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_marks_a_pending_row_as_published_on_success(): void
    {
        $row = $this->makeRow('subscription.state-changed');
        $this->bindDispatcher(['subscription.state-changed' => $this->stubHandler()]);

        $this->runJob();

        $row->refresh();
        $this->assertNotNull($row->published_at);
        $this->assertNull($row->failed_at);
        $this->assertSame(1, $row->attempts);
        $this->assertNull($row->last_error);
        $this->assertNull($row->next_attempt_at);
    }

    #[Test]
    public function unmapped_event_is_marked_published_immediately(): void
    {
        $row = $this->makeRow('subscription.no-handler-yet');
        $this->bindDispatcher([]);

        $this->runJob();

        $row->refresh();
        $this->assertNotNull($row->published_at);
        $this->assertSame(1, $row->attempts);
    }

    #[Test]
    public function failing_handler_increments_attempts_and_schedules_retry(): void
    {
        $row = $this->makeRow('subscription.state-changed');
        $this->bindDispatcher(['subscription.state-changed' => $this->throwingHandler('first failure')]);

        $this->runJob();

        $row->refresh();
        $this->assertNull($row->published_at);
        $this->assertNull($row->failed_at);
        $this->assertSame(1, $row->attempts);
        $this->assertSame('first failure', $row->last_error);
        $this->assertNotNull($row->next_attempt_at);
        $this->assertTrue($row->next_attempt_at->greaterThan(Carbon::now()->addSeconds(30)));
        $this->assertTrue($row->next_attempt_at->lessThan(Carbon::now()->addSeconds(120)));
    }

    #[Test]
    public function third_failure_marks_row_as_failed_terminal(): void
    {
        $row = $this->makeRow('subscription.state-changed');
        $row->attempts = 2;
        $row->save();

        $this->bindDispatcher(['subscription.state-changed' => $this->throwingHandler('third failure')]);

        $this->runJob();

        $row->refresh();
        $this->assertNull($row->published_at);
        $this->assertNotNull($row->failed_at);
        $this->assertSame(3, $row->attempts);
        $this->assertSame('third failure', $row->last_error);
        $this->assertNull($row->next_attempt_at);
    }

    #[Test]
    public function rows_with_future_next_attempt_at_are_skipped(): void
    {
        $row = $this->makeRow('subscription.state-changed');
        $row->next_attempt_at = Carbon::now()->addMinutes(5);
        $row->save();

        $this->bindDispatcher(['subscription.state-changed' => $this->stubHandler()]);

        $this->runJob();

        $row->refresh();
        $this->assertNull($row->published_at);
        $this->assertSame(0, $row->attempts);
    }

    #[Test]
    public function already_published_rows_are_skipped(): void
    {
        $row = $this->makeRow('subscription.state-changed');
        $row->published_at = Carbon::now()->subMinute();
        $row->save();

        $this->bindDispatcher(['subscription.state-changed' => $this->throwingHandler('would fail if processed')]);

        $this->runJob();

        $row->refresh();
        $this->assertSame(0, $row->attempts);
    }

    #[Test]
    public function terminally_failed_rows_are_skipped(): void
    {
        $row = $this->makeRow('subscription.state-changed');
        $row->failed_at = Carbon::now()->subMinute();
        $row->attempts = 3;
        $row->save();

        $this->bindDispatcher(['subscription.state-changed' => $this->stubHandler()]);

        $this->runJob();

        $row->refresh();
        $this->assertSame(3, $row->attempts);
        $this->assertNull($row->published_at);
    }

    #[Test]
    public function it_processes_multiple_pending_rows_in_one_run(): void
    {
        $r1 = $this->makeRow('subscription.state-changed');
        $r2 = $this->makeRow('subscription.state-changed');
        $r3 = $this->makeRow('subscription.state-changed');
        $this->bindDispatcher(['subscription.state-changed' => $this->stubHandler()]);

        $this->runJob();

        foreach ([$r1, $r2, $r3] as $r) {
            $r->refresh();
            $this->assertNotNull($r->published_at);
        }
    }

    private function runJob(): void
    {
        $job = new PublishOutboxEventsJob;
        $job->handle($this->app->make(OutboxEventDispatcher::class));
    }

    private function makeRow(string $eventType): BillingOutboxEvent
    {
        return BillingOutboxEvent::create([
            'aggregate_type' => 'App\\Models\\Billing\\Subscription',
            'aggregate_id' => '01J0000000000000000000ABCD',
            'event_type' => $eventType,
            'payload' => ['k' => 'v'],
        ])->refresh();
    }

    /**
     * @param  array<string, class-string<OutboxEventHandler>>  $handlerMap
     */
    private function bindDispatcher(array $handlerMap): void
    {
        $this->app->instance(
            OutboxEventDispatcher::class,
            new OutboxEventDispatcher($handlerMap, $this->app),
        );
    }

    /**
     * @return class-string<OutboxEventHandler>
     */
    private function stubHandler(): string
    {
        $h = new class implements OutboxEventHandler
        {
            public function handle(BillingOutboxEvent $event): void {}
        };
        $this->app->instance($h::class, $h);

        return $h::class;
    }

    /**
     * @return class-string<OutboxEventHandler>
     */
    private function throwingHandler(string $message): string
    {
        $h = new class($message) implements OutboxEventHandler
        {
            public function __construct(private readonly string $message) {}

            public function handle(BillingOutboxEvent $event): void
            {
                throw new RuntimeException($this->message);
            }
        };
        $this->app->instance($h::class, $h);

        return $h::class;
    }
}
