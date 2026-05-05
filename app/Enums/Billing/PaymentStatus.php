<?php

declare(strict_types=1);

namespace App\Enums\Billing;

/**
 * Payment lifecycle states (per attempt).
 *
 * An Invoice can have N Payment records (retries).
 *
 * Happy path:    pending ──► processing ──► succeeded
 * 3DS / SCA:     pending ──► requires_action ──► processing ──► succeeded
 * Failure:       processing ──► failed
 * Refund:        succeeded ──► refunded
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case RequiresAction = 'requires_action';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::RequiresAction => 'Requiere acción',
            self::Processing => 'Procesando',
            self::Succeeded => 'Exitoso',
            self::Failed => 'Fallido',
            self::Refunded => 'Reembolsado',
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Succeeded, self::Failed, self::Refunded => true,
            self::Pending, self::RequiresAction, self::Processing => false,
        };
    }

    public function isSuccessful(): bool
    {
        return $this === self::Succeeded;
    }
}
