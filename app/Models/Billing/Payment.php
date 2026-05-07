<?php

declare(strict_types=1);

namespace App\Models\Billing;

use App\Enums\Billing\PaymentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $invoice_id
 * @property string|null $payment_method_id
 * @property PaymentStatus $status
 * @property string $currency
 * @property int $amount_cents
 * @property string|null $stripe_payment_intent_id
 * @property string|null $stripe_charge_id
 * @property string|null $failure_code
 * @property string|null $failure_message
 * @property string $idempotency_key
 * @property Carbon|null $processed_at
 * @property array<string, mixed>|null $metadata
 */
class Payment extends Model
{
    use HasFactory;
    use HasUlids;

    protected $table = 'billing_payments';

    protected $fillable = [
        'invoice_id',
        'payment_method_id',
        'status',
        'currency',
        'amount_cents',
        'stripe_payment_intent_id',
        'stripe_charge_id',
        'failure_code',
        'failure_message',
        'idempotency_key',
        'processed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount_cents' => 'integer',
            'processed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }
}
