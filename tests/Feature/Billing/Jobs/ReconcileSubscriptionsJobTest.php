<?php

declare(strict_types=1);

namespace Tests\Feature\Billing\Jobs;

use App\Billing\Contracts\BillingGateway;
use App\Billing\DTOs\GatewaySubscription;
use App\Billing\Exceptions\GatewayException;
use App\Billing\Exceptions\GatewayNotFoundException;
use App\Enums\Billing\SubscriptionStatus;
use App\Jobs\Billing\ReconcileSubscriptionsJob;
use App\Models\Billing\Subscription;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ReconcileSubscriptionsJobTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_does_not_log_drift_when_local_and_gateway_agree(): void
    {
        $sub = $this->makeSub(SubscriptionStatus::Active, 'sub_match_1');

        $gateway = $this->mockGateway(function (MockInterface $m) use ($sub): void {
            $m->shouldReceive('retrieveSubscription')
                ->with($sub->stripe_subscription_id)
                ->andReturn($this->dto('sub_match_1', 'active', 'active'));
        });

        Log::shouldReceive('info')->once()->with('billing.reconcile.completed', Mockery::on(function (array $ctx): bool {
            return $ctx['checked'] === 1 && $ctx['drifts_detected'] === 0;
        }));

        (new ReconcileSubscriptionsJob)->handle($gateway);
    }

    #[Test]
    public function it_logs_drift_when_statuses_disagree(): void
    {
        $sub = $this->makeSub(SubscriptionStatus::Active, 'sub_mismatch_1');

        $gateway = $this->mockGateway(function (MockInterface $m) use ($sub): void {
            $m->shouldReceive('retrieveSubscription')
                ->with($sub->stripe_subscription_id)
                ->andReturn($this->dto('sub_mismatch_1', 'past_due', 'past_due'));
        });

        Log::shouldReceive('warning')->once()->with('billing.reconcile.drift.status_mismatch', Mockery::on(function (array $ctx) use ($sub): bool {
            return $ctx['subscription_id'] === $sub->id
                && $ctx['local_status'] === 'active'
                && $ctx['gateway_status'] === 'past_due';
        }));
        Log::shouldReceive('info')->once()->with('billing.reconcile.completed', Mockery::on(function (array $ctx): bool {
            return $ctx['drifts_detected'] === 1;
        }));

        (new ReconcileSubscriptionsJob)->handle($gateway);
    }

    #[Test]
    public function it_logs_drift_when_gateway_returns_unmapped_status(): void
    {
        $sub = $this->makeSub(SubscriptionStatus::Trialing, 'sub_unmapped_1');

        $gateway = $this->mockGateway(function (MockInterface $m) use ($sub): void {
            $m->shouldReceive('retrieveSubscription')
                ->with($sub->stripe_subscription_id)
                ->andReturn($this->dto('sub_unmapped_1', null, 'incomplete'));
        });

        Log::shouldReceive('warning')->once()->with('billing.reconcile.drift.unmapped_gateway_status', Mockery::on(function (array $ctx): bool {
            return $ctx['gateway_raw_status'] === 'incomplete';
        }));
        Log::shouldReceive('info')->once()->with('billing.reconcile.completed', Mockery::any());

        (new ReconcileSubscriptionsJob)->handle($gateway);
    }

    #[Test]
    public function it_logs_drift_when_subscription_not_found_at_gateway(): void
    {
        $sub = $this->makeSub(SubscriptionStatus::Active, 'sub_gone_1');

        $gateway = $this->mockGateway(function (MockInterface $m) use ($sub): void {
            $m->shouldReceive('retrieveSubscription')
                ->with($sub->stripe_subscription_id)
                ->andThrow(new GatewayNotFoundException('not found'));
        });

        Log::shouldReceive('warning')->once()->with('billing.reconcile.drift.not_found', Mockery::on(function (array $ctx) use ($sub): bool {
            return $ctx['subscription_id'] === $sub->id;
        }));
        Log::shouldReceive('info')->once()->with('billing.reconcile.completed', Mockery::any());

        (new ReconcileSubscriptionsJob)->handle($gateway);
    }

    #[Test]
    public function transient_gateway_errors_are_logged_as_error_not_drift(): void
    {
        $sub = $this->makeSub(SubscriptionStatus::Active, 'sub_transient_1');

        $gateway = $this->mockGateway(function (MockInterface $m) use ($sub): void {
            $m->shouldReceive('retrieveSubscription')
                ->with($sub->stripe_subscription_id)
                ->andThrow(new GatewayException('rate limited'));
        });

        Log::shouldReceive('error')->once()->with('billing.reconcile.gateway_error', Mockery::on(function (array $ctx): bool {
            return $ctx['error'] === 'rate limited';
        }));
        Log::shouldReceive('info')->once()->with('billing.reconcile.completed', Mockery::on(function (array $ctx): bool {
            return $ctx['drifts_detected'] === 0;
        }));

        (new ReconcileSubscriptionsJob)->handle($gateway);
    }

    #[Test]
    public function it_skips_canceled_subscriptions(): void
    {
        $canceled = $this->makeSub(SubscriptionStatus::Canceled, 'sub_canceled_1');

        $gateway = $this->mockGateway(function (MockInterface $m): void {
            $m->shouldNotReceive('retrieveSubscription');
        });

        Log::shouldReceive('info')->once()->with('billing.reconcile.completed', Mockery::on(function (array $ctx): bool {
            return $ctx['checked'] === 0;
        }));

        (new ReconcileSubscriptionsJob)->handle($gateway);
    }

    #[Test]
    public function it_skips_subscriptions_without_stripe_id(): void
    {
        $local_only = Subscription::factory()->create([
            'status' => SubscriptionStatus::Active,
            'stripe_subscription_id' => null,
        ]);

        $gateway = $this->mockGateway(function (MockInterface $m): void {
            $m->shouldNotReceive('retrieveSubscription');
        });

        Log::shouldReceive('info')->once()->with('billing.reconcile.completed', Mockery::on(function (array $ctx): bool {
            return $ctx['checked'] === 0;
        }));

        (new ReconcileSubscriptionsJob)->handle($gateway);
    }

    private function makeSub(SubscriptionStatus $status, string $stripeSubId): Subscription
    {
        return Subscription::factory()->create([
            'status' => $status,
            'stripe_subscription_id' => $stripeSubId,
        ]);
    }

    /**
     * @param  callable(MockInterface): void  $bindings
     */
    private function mockGateway(callable $bindings): BillingGateway
    {
        /** @var BillingGateway&MockInterface $mock */
        $mock = Mockery::mock(BillingGateway::class);
        $bindings($mock);

        return $mock;
    }

    private function dto(string $gatewayId, ?string $status, string $rawStatus): GatewaySubscription
    {
        return new GatewaySubscription(
            gatewayId: $gatewayId,
            gatewayCustomerId: 'cus_test',
            status: $status,
            rawStatus: $rawStatus,
            items: [],
            currentPeriodStart: new DateTimeImmutable,
            currentPeriodEnd: new DateTimeImmutable,
            trialEnd: null,
            cancelAt: null,
            canceledAt: null,
            cancelAtPeriodEnd: false,
            metadata: [],
        );
    }
}
