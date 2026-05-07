<?php

declare(strict_types=1);

namespace App\Models\Billing;

use App\Enums\Billing\PaymentMethodType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $customer_id
 * @property PaymentMethodType $type
 * @property string|null $stripe_payment_method_id
 * @property bool $is_default
 * @property string|null $brand
 * @property string|null $last4
 * @property int|null $exp_month
 * @property int|null $exp_year
 * @property string|null $cardholder_name
 * @property array<string, mixed>|null $metadata
 */
class PaymentMethod extends Model
{
    use HasFactory;
    use HasUlids;
    use SoftDeletes;

    protected $table = 'billing_payment_methods';

    protected $fillable = [
        'customer_id',
        'type',
        'stripe_payment_method_id',
        'is_default',
        'brand',
        'last4',
        'exp_month',
        'exp_year',
        'cardholder_name',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'type' => PaymentMethodType::class,
            'is_default' => 'boolean',
            'exp_month' => 'integer',
            'exp_year' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'payment_method_id');
    }
}
