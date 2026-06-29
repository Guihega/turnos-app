<?php

declare(strict_types=1);

namespace App\Models\Billing;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * IdempotencyKey: registro local de una clave de idempotencia usada
 * contra un gateway de billing.
 *
 * Ver migration y ADR-016 para semántica completa.
 *
 * @property string $id
 * @property string|null $customer_id
 * @property string $operation
 * @property string $gateway
 * @property string $idempotency_key
 * @property string $request_hash
 * @property array<string, mixed>|null $response_snapshot
 * @property Carbon $created_at
 * @property Carbon $expires_at
 */
class IdempotencyKey extends Model
{
    use HasFactory;
    use HasUlids;

    protected $table = 'billing_idempotency_keys';

    /**
     * Estos timestamps los gobierna la migration (created_at via
     * useCurrent, expires_at via Action). Sin updated_at.
     */
    public $timestamps = false;

    protected $fillable = [
        'customer_id',
        'operation',
        'gateway',
        'idempotency_key',
        'request_hash',
        'response_snapshot',
        'created_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'response_snapshot' => 'array',
            'created_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    // ── Relationships ──

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    // ── Scopes ──

    /**
     * Solo keys cuyo expires_at está en el futuro.
     *
     * @param  Builder<self>  $query
     */
    public function scopeNotExpired(Builder $query): void
    {
        $query->where('expires_at', '>', now());
    }

    /**
     * Solo keys ya vencidas. Usado por el cleanup job.
     *
     * @param  Builder<self>  $query
     */
    public function scopeExpired(Builder $query): void
    {
        $query->where('expires_at', '<=', now());
    }
}
