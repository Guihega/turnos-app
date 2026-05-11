<?php

declare(strict_types=1);

namespace App\Actions\Billing\Concerns;

use App\Enums\Billing\Gateway;
use App\Models\Billing\IdempotencyKey;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Shared idempotency-key handling for billing write Actions.
 *
 * Per ADR-016:
 *
 *   - Each logical operation (create_customer, create_subscription, ...)
 *     produces a deterministic request hash from its input payload.
 *
 *   - findOrCreateKey() looks up an existing non-expired IdempotencyKey
 *     by (operation, gateway, request_hash) — and optionally
 *     customer_id when known. If found, the caller can short-circuit
 *     and return the cached response_snapshot instead of re-invoking
 *     the gateway. If not found, a fresh ULID is minted and persisted.
 *
 *   - hashRequest() canonicalizes the payload so semantically-identical
 *     inputs produce identical hashes regardless of array key order.
 *
 * TTL defaults to 7 days; configurable via
 * config('billing.idempotency.ttl_days') in sub-paso 6.
 */
trait GeneratesIdempotencyKeys
{
    /**
     * Look up an existing idempotency key for this logical request, or
     * create a fresh one. The returned key's $idempotency_key field is
     * what the Action forwards to the gateway adapter.
     *
     * @param  array<string, mixed>  $payload  used to compute request_hash
     */
    protected function findOrCreateIdempotencyKey(
        string $operation,
        Gateway $gateway,
        array $payload,
        ?string $customerId = null,
    ): IdempotencyKey {
        $hash = $this->hashRequest($payload);

        $existing = IdempotencyKey::query()
            ->where('operation', $operation)
            ->where('gateway', $gateway->value)
            ->where('request_hash', $hash)
            ->when($customerId !== null, fn ($q) => $q->where('customer_id', $customerId))
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return IdempotencyKey::create([
            'customer_id' => $customerId,
            'operation' => $operation,
            'gateway' => $gateway->value,
            'idempotency_key' => (string) Str::ulid(),
            'request_hash' => $hash,
            'response_snapshot' => null,
            'expires_at' => Carbon::now()->addDays(
                (int) config('billing.idempotency.ttl_days', 7)
            ),
        ]);
    }

    /**
     * Deterministic sha256 of a normalized payload: keys sorted
     * recursively, JSON encoded with stable flags.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function hashRequest(array $payload): string
    {
        $normalized = $this->canonicalize($payload);
        $json = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return hash('sha256', $json !== false ? $json : '');
    }

    /**
     * Recursively sort array keys so {a:1,b:2} and {b:2,a:1} produce
     * the same JSON, hence the same hash. Lists (numeric keys) keep
     * their order.
     */
    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $isList = array_is_list($value);
        $out = [];
        foreach ($value as $k => $v) {
            $out[$k] = $this->canonicalize($v);
        }
        if (! $isList) {
            ksort($out);
        }

        return $out;
    }

    /**
     * Persist the gateway response back into the idempotency record so
     * future retries can short-circuit. Called by Actions after the
     * gateway returns successfully.
     *
     * @param  array<string, mixed>  $snapshot
     */
    protected function snapshotResponse(IdempotencyKey $key, array $snapshot): void
    {
        $key->update(['response_snapshot' => $snapshot]);
    }
}
