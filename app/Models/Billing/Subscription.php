<?php

declare(strict_types=1);

namespace App\Models\Billing;

use App\Enums\Billing\SubscriptionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $customer_id
 * @property string $plan_id
 * @property string|null $price_id
 * @property SubscriptionStatus $status
 * @property string|null $stripe_subscription_id
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $current_period_start
 * @property Carbon|null $current_period_end
 * @property Carbon|null $cancel_at
 * @property Carbon|null $canceled_at
 * @property Carbon|null $paused_at
 * @property array<string, mixed>|null $metadata
 */
class Subscription extends Model
{
    use HasFactory;
    use HasUlids;
    use SoftDeletes;

    protected $table = 'billing_subscriptions';

    protected $fillable = [
        'customer_id',
        'plan_id',
        'price_id',
        'status',
        'stripe_subscription_id',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'cancel_at',
        'canceled_at',
        'paused_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'cancel_at' => 'datetime',
            'canceled_at' => 'datetime',
            'paused_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function price(): BelongsTo
    {
        return $this->belongsTo(Price::class, 'price_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SubscriptionItem::class, 'subscription_id');
    }

    public function stateTransitions(): HasMany
    {
        return $this->hasMany(SubscriptionStateTransition::class, 'subscription_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'subscription_id');
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(Entitlement::class, 'subscription_id');
    }

    /**
     * Estados que se consideran "activos" (con derecho a usar el producto).
     * Coincide con la condición del unique partial index en BD.
     *
     * @return array<int, SubscriptionStatus>
     */
    public static function activeStatuses(): array
    {
        return [
            SubscriptionStatus::Pilot,
            SubscriptionStatus::Trialing,
            SubscriptionStatus::Active,
            SubscriptionStatus::PastDue,
            SubscriptionStatus::Paused,
        ];
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::activeStatuses(), strict: true);
    }
}
