<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Billing\SubscriptionStatus;
use App\Models\Billing\Entitlement;
use App\Models\Billing\Subscription;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * VerifyTenantsBilling — Audits the "every tenant has well-formed pilot
 * billing" invariant (PR-S4). Read-only.
 *
 * For each tenant it checks, in order:
 *   1. MISSING_CUSTOMER       — no billing Customer (1:1) at all.
 *   2. MISSING_SUBSCRIPTION   — Customer exists but no Subscription
 *                               (the orphaned-customer state a partial
 *                               onboarding or a soft rollback would leave).
 *   3. INACTIVE_SUBSCRIPTION  — Subscription exists but its status is not in
 *                               activeSlotValues() (e.g. canceled): it no
 *                               longer occupies an active slot.
 *   4. MISSING_ENTITLEMENTS   — Subscription exists but has no plan-sourced
 *                               entitlements materialized.
 *
 * Exit code is FAILURE if any violation is found, SUCCESS otherwise (an empty
 * tenant set satisfies the invariant vacuously). Safe to run in CI/cron.
 *
 * Manual run:
 *   php artisan billing:verify-tenants
 *   php artisan billing:verify-tenants --json
 */
class VerifyTenantsBilling extends Command
{
    protected $signature = 'billing:verify-tenants
        {--json : Emit a machine-readable JSON report instead of human output}';

    protected $description = 'Audit the pilot-billing invariant for every tenant (read-only)';

    public function handle(): int
    {
        $slotValues = SubscriptionStatus::activeSlotValues();

        /** @var Collection<int, Tenant> $tenants */
        $tenants = Tenant::with(['customer', 'subscription'])->get();

        $violations = [];

        foreach ($tenants as $tenant) {
            assert($tenant instanceof Tenant);
            $problems = [];

            if ($tenant->customer === null) {
                $problems[] = 'MISSING_CUSTOMER';
            } else {
                /** @var ?Subscription $subscription */
                $subscription = $tenant->subscription;

                if ($subscription === null) {
                    $problems[] = 'MISSING_SUBSCRIPTION';
                } else {
                    if (! in_array($subscription->status->value, $slotValues, true)) {
                        $problems[] = 'INACTIVE_SUBSCRIPTION';
                    }

                    $hasPlanEntitlements = Entitlement::query()
                        ->where('subscription_id', $subscription->id)
                        ->where('source', Entitlement::SOURCE_PLAN)
                        ->exists();

                    if (! $hasPlanEntitlements) {
                        $problems[] = 'MISSING_ENTITLEMENTS';
                    }
                }
            }

            if ($problems !== []) {
                $violations[] = [
                    'tenant_id' => $tenant->id,
                    'slug' => $tenant->slug,
                    'problems' => $problems,
                ];
            }
        }

        return $this->option('json')
            ? $this->renderJson($tenants->count(), $violations)
            : $this->renderHuman($tenants->count(), $violations);
    }

    /**
     * @param  list<array{tenant_id: string, slug: string, problems: list<string>}>  $violations
     */
    private function renderHuman(int $total, array $violations): int
    {
        $this->info('╔══════════════════════════════════════════╗');
        $this->info('║  Olinora — Verify Tenants Billing        ║');
        $this->info('╚══════════════════════════════════════════╝');
        $this->info('');
        $this->info("→ Tenants audited: {$total}");

        if ($violations === []) {
            $this->info('✓ Invariant holds: every tenant has well-formed pilot billing.');

            return self::SUCCESS;
        }

        $count = count($violations);
        $this->error("✕ Violations found: {$count}");
        $this->info('');

        foreach ($violations as $v) {
            $problems = implode(', ', $v['problems']);
            $this->warn("  · {$v['slug']} (id={$v['tenant_id']}): {$problems}");
        }

        return self::FAILURE;
    }

    /**
     * @param  list<array{tenant_id: string, slug: string, problems: list<string>}>  $violations
     */
    private function renderJson(int $total, array $violations): int
    {
        $this->line((string) json_encode([
            'audited' => $total,
            'violations' => count($violations),
            'details' => $violations,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $violations === [] ? self::SUCCESS : self::FAILURE;
    }
}
