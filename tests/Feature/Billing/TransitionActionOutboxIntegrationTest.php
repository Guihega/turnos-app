<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Actions\Billing\TransitionSubscriptionAction;
use App\Enums\Billing\SubscriptionStatus;
use App\Exceptions\Billing\InvalidStateTransitionException;
use App\Models\Billing\BillingOutboxEvent;
use App\Models\Billing\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TransitionActionOutboxIntegrationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_successful_transition_persists_an_outbox_row(): void
    {
        /** @var Subscription $sub */
        $sub = Subscription::factory()->create(['status' => SubscriptionStatus::Trialing]);

        /** @var TransitionSubscriptionAction $action */
        $action = $this->app->make(TransitionSubscriptionAction::class);

        $action->execute(
            subscription: $sub,
            to: SubscriptionStatus::Active,
            reason: 'trial_converted',
            actor: 'system',
        );

        $this->assertSame(1, BillingOutboxEvent::count());

        $row = BillingOutboxEvent::query()->sole();
        $this->assertSame(Subscription::class, $row->aggregate_type);
        $this->assertSame((string) $sub->id, $row->aggregate_id);
        $this->assertSame('subscription.state-changed', $row->event_type);
        $this->assertNull($row->published_at);
        $this->assertNull($row->failed_at);

        $payload = $row->payload;
        $this->assertSame('trialing', $payload['from']);
        $this->assertSame('active', $payload['to']);
        $this->assertSame('trial_converted', $payload['reason']);
    }

    #[Test]
    public function a_failed_transition_leaves_no_outbox_row(): void
    {
        /** @var Subscription $sub */
        $sub = Subscription::factory()->create(['status' => SubscriptionStatus::Canceled]);

        /** @var TransitionSubscriptionAction $action */
        $action = $this->app->make(TransitionSubscriptionAction::class);

        try {
            $action->execute(
                subscription: $sub,
                to: SubscriptionStatus::Active,
                reason: 'invalid',
            );
            $this->fail('Expected InvalidStateTransitionException');
        } catch (InvalidStateTransitionException) {
            // expected
        }

        $this->assertSame(0, BillingOutboxEvent::count());
    }

    #[Test]
    public function a_same_state_noop_does_not_write_to_outbox(): void
    {
        /** @var Subscription $sub */
        $sub = Subscription::factory()->create(['status' => SubscriptionStatus::Active]);

        /** @var TransitionSubscriptionAction $action */
        $action = $this->app->make(TransitionSubscriptionAction::class);

        $action->execute(
            subscription: $sub,
            to: SubscriptionStatus::Active,
            reason: 'noop',
        );

        $this->assertSame(0, BillingOutboxEvent::count());
    }

    #[Test]
    public function outbox_row_is_rolled_back_if_transaction_fails(): void
    {
        /** @var Subscription $sub */
        $sub = Subscription::factory()->create(['status' => SubscriptionStatus::Trialing]);

        /** @var TransitionSubscriptionAction $action */
        $action = $this->app->make(TransitionSubscriptionAction::class);

        DB::beginTransaction();
        try {
            $action->execute(
                subscription: $sub,
                to: SubscriptionStatus::Active,
                reason: 'will_be_rolled_back',
            );
            $this->assertSame(1, BillingOutboxEvent::count(), 'row exists inside tx');
        } finally {
            DB::rollBack();
        }

        $this->assertSame(0, BillingOutboxEvent::count(), 'row gone after rollback');
    }
}
