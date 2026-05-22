<?php

declare(strict_types=1);

namespace App\Billing\DTOs;

/**
 * Immutable snapshot of a tenant's effective entitlements, keyed by
 * feature code.
 *
 * Produced by EntitlementService::for(). Each entry holds the raw
 * column values resolved for one feature code, after merging the
 * plan-derived entitlement with any active operational grant
 * (grant wins). The product consumes this via the typed accessors;
 * it never inspects the underlying rows.
 *
 * Accessor semantics:
 *   - has()    reads value_boolean. Absent code or null => false.
 *   - quota()  reads value_numeric. Absent code => 0 (blocks). A
 *              materialized -1 means unlimited (per FeatureType docblock).
 *   - string() reads value_string. Absent code or null => ''.
 *
 * The 0-vs-(-1) distinction matters: 0 is the "no entitlement" default
 * (deny), -1 is an explicit unlimited quota. Callers comparing usage
 * (e.g. abort_if($used >= $max)) must treat -1 as no ceiling.
 */
final readonly class ResolvedEntitlements
{
    /**
     * @param  array<string, array{numeric: int|null, boolean: bool|null, string: string|null, reset_period: string|null}>  $values
     *                                                                                                                               Map of feature code => resolved column values.
     */
    public function __construct(
        private array $values,
    ) {}

    /**
     * Whether a boolean feature is enabled for this tenant.
     */
    public function has(string $code): bool
    {
        return $this->values[$code]['boolean'] ?? false;
    }

    /**
     * Numeric quota for a feature. 0 when no entitlement exists (deny);
     * -1 when the entitlement is explicitly unlimited.
     */
    public function quota(string $code): int
    {
        return $this->values[$code]['numeric'] ?? 0;
    }

    /**
     * String-valued entitlement (e.g. support.tier). Empty string when absent.
     */
    public function string(string $code): string
    {
        return $this->values[$code]['string'] ?? '';
    }

    /**
     * Reset period for a quota (e.g. 'monthly'), or null if not periodic.
     */
    public function resetPeriod(string $code): ?string
    {
        return $this->values[$code]['reset_period'] ?? null;
    }

    /**
     * Whether any entitlement is resolved for the given code.
     *
     * Distinguishes "code absent" from "code present with a falsy value"
     * — useful for the dual-read fallback decision in EntitlementService.
     */
    public function defines(string $code): bool
    {
        return array_key_exists($code, $this->values);
    }

    /**
     * All resolved feature codes.
     *
     * @return list<string>
     */
    public function codes(): array
    {
        return array_keys($this->values);
    }
}
