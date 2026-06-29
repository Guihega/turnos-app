<?php

declare(strict_types=1);

namespace App\Enums\Billing;

/**
 * Invoice lifecycle states.
 *
 * Happy path:   draft ──► open ──► paid
 * Dunning:      open  ──► uncollectible
 * Admin void:   open  ──► void
 *
 * draft = invoice being assembled (line items added). Not yet finalized.
 * open  = finalized and awaiting payment.
 * paid  = fully paid via one or more Payment records.
 * void  = administratively cancelled (with reason in audit log).
 * uncollectible = dunning exhausted, written off.
 */
enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Paid = 'paid';
    case Void = 'void';
    case Uncollectible = 'uncollectible';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Open => 'Pendiente',
            self::Paid => 'Pagada',
            self::Void => 'Anulada',
            self::Uncollectible => 'Incobrable',
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Paid, self::Void, self::Uncollectible => true,
            self::Draft, self::Open => false,
        };
    }
}
