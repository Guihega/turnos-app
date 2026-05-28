<?php

declare(strict_types=1);

namespace App\Events\Billing;

use App\Models\Billing\PaymentMethod;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched after a PaymentMethod has been attached in both the
 * gateway and the local DB (post-commit). Listeners may safely query
 * the PaymentMethod and its parent Customer.
 *
 * $wasSetAsDefault carries whether this attach also promoted the PM
 * to the customer's default — useful for listeners that surface
 * "card on file" badges or send confirmation emails.
 *
 * @see App\Actions\Billing\UpdatePaymentMethodAction
 * @see docs/adr/ADR-016-billing-write-contract-and-create-flows.md
 */
final class PaymentMethodAttached
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly PaymentMethod $paymentMethod,
        public readonly bool $wasSetAsDefault,
    ) {}
}
