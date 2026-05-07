<?php

declare(strict_types=1);

namespace App\Models\Billing;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $invoice_id
 * @property string $description
 * @property int $quantity
 * @property int $unit_amount_cents
 * @property int $amount_cents
 * @property string|null $price_id
 * @property Carbon|null $period_start
 * @property Carbon|null $period_end
 * @property array<string, mixed>|null $metadata
 */
class InvoiceLine extends Model
{
    use HasFactory;
    use HasUlids;

    protected $table = 'billing_invoice_lines';

    protected $fillable = [
        'invoice_id',
        'description',
        'quantity',
        'unit_amount_cents',
        'amount_cents',
        'price_id',
        'period_start',
        'period_end',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_amount_cents' => 'integer',
            'amount_cents' => 'integer',
            'period_start' => 'date',
            'period_end' => 'date',
            'metadata' => 'array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function price(): BelongsTo
    {
        return $this->belongsTo(Price::class, 'price_id');
    }
}
