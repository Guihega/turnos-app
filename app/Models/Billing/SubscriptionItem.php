<?php

declare(strict_types=1);

namespace App\Models\Billing;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $subscription_id
 * @property string $price_id
 * @property string $kind
 * @property int $quantity
 * @property string|null $stripe_subscription_item_id
 * @property array<string, mixed>|null $metadata
 */
class SubscriptionItem extends Model
{
    use HasFactory;
    use HasUlids;
    use SoftDeletes;

    public const KIND_SUBSCRIPTION = 'subscription';

    public const KIND_ADDON = 'addon';

    protected $table = 'billing_subscription_items';

    /**
     * Default attribute values, applied to new in-memory instances before
     * persistence. Mirrors the DB-level defaults in the migration so the
     * model's hydrated state matches the persisted state without needing
     * a refresh() round-trip.
     *
     * The literal 'subscription' is identical to self::KIND_SUBSCRIPTION
     * but avoids the PHP property-initializer caveat with class constants.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'kind' => 'subscription',
        'quantity' => 1,
    ];

    protected $fillable = [
        'subscription_id',
        'price_id',
        'kind',
        'quantity',
        'stripe_subscription_item_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id');
    }

    public function price(): BelongsTo
    {
        return $this->belongsTo(Price::class, 'price_id');
    }
}
