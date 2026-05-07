<?php

declare(strict_types=1);

namespace App\Enums\Billing;

/**
 * Tipos de método de pago soportados.
 *
 * Card es el único activo en MVP. El resto se habilitan según expansión:
 *   - BankTransfer: SPEI (MX), PSE (CO), CBU (AR) — Fase 4 con Mercado Pago.
 *   - Oxxo: convenience store cash-in (MX) — vía Stripe partner.
 *   - Cash: cobros manuales presenciales (sin gateway).
 *   - Manual: ajustes administrativos sin movimiento real de dinero.
 */
enum PaymentMethodType: string
{
    case Card = 'card';
    case BankTransfer = 'bank_transfer';
    case Oxxo = 'oxxo';
    case Cash = 'cash';
    case Manual = 'manual';
}
