<?php

declare(strict_types=1);

namespace App\Mail\Billing;

use App\Models\Billing\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email sent when a subscription transitions to the suspended state.
 *
 * Fired when dunning has exhausted: Stripe Smart Retries gave up on
 * the payment, and SubscriptionUpdatedHandler mapped Stripe's `unpaid`
 * status to our `Suspended`. The tenant has lost access and is told
 * how to recover the subscription (contact + payment method update).
 */
final class BillingSubscriptionSuspendedNotification extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Subscription $subscription,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tu suscripción ha sido suspendida',
        );
    }

    public function content(): Content
    {
        $plan = $this->subscription->plan;
        $planName = $plan !== null ? $plan->name : 'tu plan';

        return new Content(
            markdown: 'emails.billing.suspended',
            with: [
                'planName' => $planName,
                'subscriptionId' => $this->subscription->id,
            ],
        );
    }
}
