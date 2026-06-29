<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Billing\OnboardPilotAction;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * BackfillExistingTenants — Provisions pilot billing for tenants that have none.
 *
 * Fase B backfill driver. Iterates every Tenant WITHOUT a billing Customer
 * (whereDoesntHave('customer')) and runs OnboardPilotAction on it, creating a
 * local pilot Customer + Subscription (no payment gateway). This brings legacy
 * tenants — created via OnboardingController before the onboarding hook existed —
 * onto the pilot plan, so entitlements get materialized and the dual-read
 * fallback stops being the only safety net.
 *
 * OnboardPilotAction is NOT wrapped in an outer transaction (see its docblock:
 * it would fight the post-commit SubscriptionCreated dispatch). Each tenant is
 * processed independently and idempotently; a failure on one tenant is logged
 * and skipped, the loop continues. Re-running is safe (idempotent on tenant_id).
 *
 * Manual run:
 *   php artisan billing:backfill-existing-tenants --dry-run
 *   php artisan billing:backfill-existing-tenants
 *   php artisan billing:backfill-existing-tenants --force   (skip prod confirm)
 */
class BackfillExistingTenants extends Command
{
    protected $signature = 'billing:backfill-existing-tenants
        {--dry-run : List tenants that would be onboarded without writing anything}
        {--force : Skip confirmation prompt in production}';

    protected $description = 'Provision pilot billing (Customer + Subscription) for tenants that have none';

    public function handle(OnboardPilotAction $onboardPilot): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun && app()->isProduction() && ! $this->option('force')) {
            if (! $this->confirm('⚠ This will provision pilot billing for tenants in PRODUCTION. Continue?')) {
                $this->info('Aborted.');

                return self::SUCCESS;
            }
        }

        $this->info('╔══════════════════════════════════════════╗');
        $this->info('║  Olinora — Backfill Existing Tenants     ║');
        $this->info('╚══════════════════════════════════════════╝');
        $this->info('');

        if ($dryRun) {
            $this->info('→ DRY-RUN: no data will be written');
        }

        /** @var Collection<int, Tenant> $tenants */
        $tenants = Tenant::whereDoesntHave('customer')->get();

        if ($tenants->isEmpty()) {
            $this->info('✓ No tenants pending backfill. All tenants already have billing.');

            return self::SUCCESS;
        }

        $this->info("→ Tenants without billing: {$tenants->count()}");
        $this->info('');

        $processed = 0;
        $errors = 0;

        foreach ($tenants as $tenant) {
            /** @var Tenant $tenant */
            if ($dryRun) {
                $this->info("  · would onboard: {$tenant->slug} (id={$tenant->id})");
                $processed++;

                continue;
            }

            try {
                $onboardPilot->execute($tenant);
                $this->info("✓ onboarded: {$tenant->slug} (id={$tenant->id})");
                $processed++;
            } catch (Throwable $e) {
                $errors++;
                $this->error("✕ failed: {$tenant->slug} (id={$tenant->id}) — {$e->getMessage()}");
                Log::error('[BackfillExistingTenants] Onboard failed', [
                    'tenant_id' => $tenant->id,
                    'exception' => $e,
                ]);
            }
        }

        $skipped = $tenants->count() - $processed - $errors;

        $this->info('');
        $this->info('════════════════════════════════════════════');
        $this->info("  Processed: {$processed}   Skipped: {$skipped}   Errors: {$errors}");
        $this->info('════════════════════════════════════════════');

        if (! $dryRun) {
            Log::info('[BackfillExistingTenants] Completed', [
                'processed' => $processed,
                'errors' => $errors,
            ]);
        }

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
