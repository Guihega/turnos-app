<?php

declare(strict_types=1);

namespace App\Enums\Billing;

/**
 * Payment gateways supported by Olinora's billing module.
 *
 * Add new gateways here only after their Adapter implementation
 * has been merged. Do not add cases speculatively.
 */
enum Gateway: string
{
    case Stripe = 'stripe';
    case MercadoPago = 'mercadopago';
    case OpenPay = 'openpay';
    case Conekta = 'conekta';
    case PayPal = 'paypal';

    /**
     * Manual gateway: used for off-platform payments (wire transfer,
     * cash, custom enterprise contracts). No webhook integration.
     */
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Stripe => 'Stripe',
            self::MercadoPago => 'Mercado Pago',
            self::OpenPay => 'OpenPay',
            self::Conekta => 'Conekta',
            self::PayPal => 'PayPal',
            self::Manual => 'Manual',
        };
    }

    /**
     * Whether this gateway requires webhook signature validation.
     * Manual gateway does not.
     */
    public function requiresWebhookValidation(): bool
    {
        return $this !== self::Manual;
    }
}
