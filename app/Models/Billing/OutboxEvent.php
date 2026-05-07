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
 * @property string $aggregate_type
 * @property string $aggregate_id
 * @property string $event_type
 * @property array<string, mixed> $payload
 * @property Carbon|null $published_at
 * @property Carbon|null $failed_at
 * @property int $attempts
 * @property string|null $last_error
 */
class OutboxEvent extends Model
{
    use HasFactory;
    use HasUlids;

    protected $table = 'billing_outbox_events';

    protected $attributes = [
        'attempts' => 0,
    ];

    protected $fillable = [
        'aggregate_type',
        'aggregate_id',
        'event_type',
        'payload',
        'published_at',
        'failed_at',
        'attempts',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'published_at' => 'datetime',
            'failed_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    /**
     * Events the publisher should pick up: not yet published and not
     * marked as terminally failed.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('published_at')->whereNull('failed_at');
    }

    /**
     * Events successfully published to consumers.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at');
    }

    /**
     * Events that exhausted retries and require manual intervention.
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->whereNotNull('failed_at');
    }

    /**
     * Events emitted by a specific aggregate type ('Subscription', 'Invoice', ...).
     */
    public function scopeForAggregate(Builder $query, string $aggregateType): Builder
    {
        return $query->where('aggregate_type', $aggregateType);
    }
}
