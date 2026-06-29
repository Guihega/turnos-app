<?php

declare(strict_types=1);

namespace App\Models\Billing;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Audit append-only de transiciones de estado de Subscriptions.
 *
 * Este modelo NO usa SoftDeletes ni timestamps automáticos. La tabla
 * tiene un trigger PostgreSQL que rechaza UPDATE y DELETE — ver migración
 * 2026_05_07_000008_add_immutable_trigger_to_billing_state_transitions.
 *
 * Solo se permite INSERT. transitioned_at se setea explícitamente al crear.
 *
 * @property string $id
 * @property string $subscription_id
 * @property string $from_status
 * @property string $to_status
 * @property string|null $reason
 * @property array<string, mixed>|null $context
 * @property Carbon $transitioned_at
 */
class SubscriptionStateTransition extends Model
{
    use HasFactory;
    use HasUlids;

    public $timestamps = false;

    protected $table = 'billing_subscription_state_transitions';

    protected $fillable = [
        'subscription_id',
        'from_status',
        'to_status',
        'reason',
        'context',
        'transitioned_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'transitioned_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id');
    }
}
