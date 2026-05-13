<?php

declare(strict_types=1);

namespace App\Models\Billing;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
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
 * @property Carbon|null $next_attempt_at
 * @property string|null $last_error
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class BillingOutboxEvent extends Model
{
    use HasUlids;

    protected $table = 'billing_outbox_events';

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'published_at' => 'datetime',
        'failed_at' => 'datetime',
        'next_attempt_at' => 'datetime',
        'attempts' => 'integer',
    ];
}
