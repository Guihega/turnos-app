<?php

declare(strict_types=1);

namespace Tests\Unit\Billing\Webhooks\Handlers;

use App\Billing\Webhooks\Handlers\TrialWillEndHandler;
use App\Events\Billing\BillingTrialWillEnd;
use App\Models\Billing\Subscription;
use App\Models\Billing\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TrialWillEndHandlerTest extends TestCase
{
    use RefreshDatabase;

    private function makeEvent(string $stripeSubId, int $trialEndTs): WebhookEvent
    {
        /** @var WebhookEvent $event */
        $event = WebhookEvent::create([
            'gateway' => 'stripe',
            'gateway_event_id' => 'evt_'.uniqid(),
            'event_type' => 'customer.subscription.trial_will_end',
            'payload' => [
                'id' => 'evt_x',
                'type' => 'customer.subscription.trial_will_end',
                'data' => [
                    'object' => [
                        'id' => $stripeSubId,
                        'trial_end' => $trialEndTs,
                    ],
                ],
            ],
            'signature_header' => 't=1,v1=dummy',
        ]);

        return $event;
    }

    #[Test]
    public function dispatches_domain_event_with_subscription_id_and_days_remaining(): void
    {
        Event::fake([BillingTrialWillEnd::class]);

        /** @var Subscription $sub */
        $sub = Subscription::factory()
            ->trialing()
            ->withStripeId('sub_trial_end')
            ->create();

        // 3 days from now in epoch seconds.
        $trialEndTs = time() + (3 * 86400);
        $event = $this->makeEvent('sub_trial_end', $trialEndTs);

        $handler = $this->app->make(TrialWillEndHandler::class);
        $handler->handle($event);

        Event::assertDispatched(BillingTrialWillEnd::class, function (BillingTrialWillEnd $e) use ($sub): bool {
            // daysRemaining can be 3 or 4 depending on rounding (ceil).
            return $e->subscriptionId === $sub->id
                && $e->daysRemaining !== null
                && $e->daysRemaining >= 2
                && $e->daysRemaining <= 4;
        });
    }

    #[Test]
    public function does_not_dispatch_when_subscription_not_owned(): void
    {
        Event::fake([BillingTrialWillEnd::class]);

        $event = $this->makeEvent('sub_stranger', time() + 86400);

        $handler = $this->app->make(TrialWillEndHandler::class);
        $handler->handle($event);

        Event::assertNotDispatched(BillingTrialWillEnd::class);
    }
}
