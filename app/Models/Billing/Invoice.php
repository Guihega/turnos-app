<?php

declare(strict_types=1);

namespace App\Models\Billing;

use App\Enums\Billing\InvoiceStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $subscription_id
 * @property string $customer_id
 * @property InvoiceStatus $status
 * @property string $invoice_number
 * @property string $currency
 * @property int $subtotal_cents
 * @property int $tax_cents
 * @property int $total_cents
 * @property int $amount_paid_cents
 * @property int $amount_due_cents
 * @property Carbon $issued_at
 * @property Carbon|null $due_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $voided_at
 * @property string|null $void_reason
 * @property string|null $stripe_invoice_id
 * @property string|null $pdf_path
 * @property array<string, mixed>|null $metadata
 */
class Invoice extends Model
{
    use HasFactory;
    use HasUlids;

    protected $table = 'billing_invoices';

    protected $fillable = [
        'subscription_id',
        'customer_id',
        'status',
        'invoice_number',
        'currency',
        'subtotal_cents',
        'tax_cents',
        'total_cents',
        'amount_paid_cents',
        'amount_due_cents',
        'issued_at',
        'due_at',
        'paid_at',
        'voided_at',
        'void_reason',
        'stripe_invoice_id',
        'pdf_path',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'subtotal_cents' => 'integer',
            'tax_cents' => 'integer',
            'total_cents' => 'integer',
            'amount_paid_cents' => 'integer',
            'amount_due_cents' => 'integer',
            'issued_at' => 'date',
            'due_at' => 'date',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class, 'invoice_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'invoice_id');
    }
}
