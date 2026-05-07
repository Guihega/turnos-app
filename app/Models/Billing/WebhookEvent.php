<?php

declare(strict_types=1);

namespace App\Models\Billing;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $gateway
 * @property string $gateway_event_id
 * @property string $event_type
 * @property array<string, mixed> $payload
 * @property string|null $signature_header
 * @property Carbon|null $processed_at
 * @property bool $needs_review
 * @property int $attempts
 * @property string|null $last_error
 * @property Carbon|null $replayed_at
 */
class WebhookEvent extends Model
{
    use HasFactory;
    use HasUlids;

    protected $table = 'billing_webhook_events';

    protected $attributes = [
        'needs_review' => false,
        'attempts' => 0,
    ];

    protected $fillable = [
        'gateway',
        'gateway_event_id',
        'event_type',
        'payload',
        'signature_header',
        'processed_at',
        'needs_review',
        'attempts',
        'last_error',
        'replayed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
            'needs_review' => 'boolean',
            'attempts' => 'integer',
            'replayed_at' => 'datetime',
        ];
    }

    /**
     * Events that have not yet been processed and don't need manual review.
     * The publisher's working set.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('processed_at')->where('needs_review', false);
    }

    /**
     * Events successfully processed.
     */
    public function scopeProcessed(Builder $query): Builder
    {
        return $query->whereNotNull('processed_at');
    }

    /**
     * Events that exhausted retries and require manual intervention.
     * What the admin panel surfaces.
     */
    public function scopeNeedsReview(Builder $query): Builder
    {
        return $query->where('needs_review', true);
    }

    /**
     * Events from a specific gateway.
     */
    public function scopeForGateway(Builder $query, string $gateway): Builder
    {
        return $query->where('gateway', $gateway);
    }
}
