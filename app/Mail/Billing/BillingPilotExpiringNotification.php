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
 * Email sent during the pilot trial expiration window.
 *
 * Triggered by NotifyPilotExpirationJob at configured offsets
 * (default: 30, 15, 7, 1 days before trial_ends_at). The subject
 * and body adapt to $daysRemaining so a single Mailable covers all
 * buckets — see MIGRATION_PLAN Fase D for the staggered cadence.
 *
 * Queueable: the job calls Mail::queue() so dispatch happens on
 * the default queue, not inline.
 */
final class BillingPilotExpiringNotification extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Subscription $subscription,
        public readonly int $daysRemaining,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectForDays($this->daysRemaining),
        );
    }

    public function content(): Content
    {
        $plan = $this->subscription->plan;
        $planName = $plan !== null ? $plan->name : 'tu plan';

        $trialEndsAt = $this->subscription->trial_ends_at;
        $trialEndsFormatted = $trialEndsAt !== null
            ? $trialEndsAt->format('d/m/Y')
            : null;

        return new Content(
            markdown: 'emails.billing.pilot-expiring',
            with: [
                'planName' => $planName,
                'subscriptionId' => $this->subscription->id,
                'daysRemaining' => $this->daysRemaining,
                'trialEndsFormatted' => $trialEndsFormatted,
            ],
        );
    }

    private function subjectForDays(int $days): string
    {
        return match (true) {
            $days >= 30 => 'Tu período de prueba termina en un mes',
            $days >= 15 => 'Tu período de prueba termina en dos semanas',
            $days >= 7 => 'Tu período de prueba termina en una semana',
            $days === 1 => 'Tu período de prueba termina mañana',
            default => "Tu período de prueba termina en {$days} días",
        };
    }
}
