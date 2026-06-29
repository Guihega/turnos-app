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
 * Email sent when a subscription enters the past_due state.
 *
 * Entry point of the dunning UX: informs the tenant that a payment
 * has failed and what to do (update payment method via portal).
 * Stripe handles the retry schedule itself (Smart Retries); this mail
 * is the human-facing nudge.
 *
 * The Mailable is queueable — Mail::queue() in the handler does the
 * actual dispatch on the default queue.
 */
final class BillingPastDueNotification extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Subscription $subscription,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tu pago no pudo procesarse',
        );
    }

    public function content(): Content
    {
        $plan = $this->subscription->plan;
        $planName = $plan !== null ? $plan->name : 'tu plan';

        return new Content(
            markdown: 'emails.billing.past-due',
            with: [
                'planName' => $planName,
                'subscriptionId' => $this->subscription->id,
            ],
        );
    }
}
