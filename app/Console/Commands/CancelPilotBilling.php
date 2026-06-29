<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Billing\TransitionSubscriptionAction;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Billing\Subscription;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * CancelPilotBilling — Cancels pilot billing for a tenant or every pilot
 * tenant (PR-S4).
 *
 * The system enforces auditing immutability at the database level (ADR-011:
 * a trigger on billing_subscription_state_transitions rejects every UPDATE
 * and DELETE). Reverting pilot billing by hard-deleting the Subscription is
 * therefore impossible without DBA intervention to disable the trigger — an
 * operational, not a product, action. This command does the product-shaped
 * equivalent instead: it CANCELS the pilot Subscription via the canonical
 * TransitionSubscriptionAction (Pilot -> Canceled is a permitted transition,
 * generates a new append-only audit row, and dispatches the proper domain
 * event) and physically deletes the materialized entitlements so access is
 * actually revoked. The Customer and the Subscription row stay, with full
 * history preserved.
 *
 * A canceled tenant is NOT re-onboardable by the backfill: it still has a
 * Customer, so whereDoesntHave('customer') excludes it. Re-enabling a former
 * pilot is a deliberate, separate operation (reactivation/new subscription),
 * out of scope here.
 *
 * Targeting (exactly one):
 *   --tenant=slug : cancel a single tenant (default, minimal blast radius).
 *   --all         : cancel every PILOT tenant. A tenant is considered pilot
 *                   only if its Customer has NO gateway ref AND was created
 *                   via CreatePilotCustomerAction — a double guard so paid
 *                   (CheckoutController/Stripe) customers are never selected.
 *
 * Manual run:
 *   php artisan billing:cancel-pilot --tenant=clinica-santa-fe --dry-run
 *   php artisan billing:cancel-pilot --tenant=clinica-santa-fe
 *   php artisan billing:cancel-pilot --all --force
 *
 * @see docs/billing/DECISIONS.md ADR-011 (immutable transitions trigger)
 */
class CancelPilotBilling extends Command
{
    private const PILOT_CREATED_VIA = 'CreatePilotCustomerAction';

    private const REASON = 'pilot canceled via billing:cancel-pilot';

    protected $signature = 'billing:cancel-pilot
        {--tenant= : Slug of a single tenant to cancel}
        {--all : Cancel every pilot tenant (gateway-free, created via the pilot action)}
        {--dry-run : Show what would be canceled without writing anything}
        {--force : Skip confirmation prompt in production}';

    protected $description = 'Cancel pilot billing (transition to Canceled + revoke entitlements) for a tenant or all pilot tenants';

    public function handle(TransitionSubscriptionAction $transition): int
    {
        $slug = $this->option('tenant');
        $all = (bool) $this->option('all');
        $dryRun = (bool) $this->option('dry-run');

        if ($slug === null && ! $all) {
            $this->error('✕ Provide either --tenant=slug or --all.');

            return self::FAILURE;
        }
        if ($slug !== null && $all) {
            $this->error('✕ --tenant and --all are mutually exclusive.');

            return self::FAILURE;
        }

        $tenants = $all ? $this->pilotTenants() : $this->singleTenant((string) $slug);
        if ($tenants === null) {
            return self::FAILURE;
        }

        if ($tenants->isEmpty()) {
            $this->info('✓ No matching tenants to cancel.');

            return self::SUCCESS;
        }

        if (! $dryRun && app()->isProduction() && ! $this->option('force')) {
            $n = $tenants->count();
            if (! $this->confirm("⚠ This will CANCEL pilot billing for {$n} tenant(s) in PRODUCTION. Continue?")) {
                $this->info('Aborted.');

                return self::SUCCESS;
            }
        }

        $this->info('╔══════════════════════════════════════════╗');
        $this->info('║  Olinora — Cancel Pilot Billing          ║');
        $this->info('╚══════════════════════════════════════════╝');
        $this->info('');

        if ($dryRun) {
            $this->info('→ DRY-RUN: no data will be written');
        }

        $canceled = 0;
        $errors = 0;

        foreach ($tenants as $tenant) {
            /** @var Tenant $tenant */
            $customer = $tenant->customer;
            if ($customer === null) {
                $this->warn("  · skip: {$tenant->slug} (id={$tenant->id}) — no customer");

                continue;
            }

            /** @var Collection<int, Subscription> $subscriptions */
            $subscriptions = $customer->subscriptions()->get();
            if ($subscriptions->isEmpty()) {
                $this->warn("  · skip: {$tenant->slug} (id={$tenant->id}) — no subscriptions");

                continue;
            }

            if ($dryRun) {
                $this->info("  · would cancel: {$tenant->slug} (id={$tenant->id})");
                $canceled++;

                continue;
            }

            try {
                DB::transaction(function () use ($subscriptions, $transition): void {
                    foreach ($subscriptions as $subscription) {
                        assert($subscription instanceof Subscription);
                        // Transition Pilot -> Canceled. Idempotent: if already
                        // Canceled the Action no-ops. Generates a new audit row
                        // (append-only) and dispatches SubscriptionStateChanged.
                        $transition->execute(
                            subscription: $subscription,
                            to: SubscriptionStatus::Canceled,
                            reason: self::REASON,
                            actor: 'billing:cancel-pilot',
                            metadata: ['triggered_by' => 'cancel-pilot-command'],
                        );
                        // Revoke access by removing the materialized entitlements.
                        // billing_entitlements has no append-only trigger; this is
                        // a physical delete.
                        $subscription->entitlements()->delete();
                    }
                });
                $this->info("✓ canceled: {$tenant->slug} (id={$tenant->id})");
                $canceled++;
            } catch (Throwable $e) {
                $errors++;
                $this->error("✕ failed: {$tenant->slug} (id={$tenant->id}) — {$e->getMessage()}");
                Log::error('[CancelPilotBilling] Cancel failed', [
                    'tenant_id' => $tenant->id,
                    'exception' => $e,
                ]);
            }
        }

        $this->info('');
        $this->info('════════════════════════════════════════════');
        $this->info("  Canceled: {$canceled}   Errors: {$errors}");
        $this->info('════════════════════════════════════════════');

        if (! $dryRun) {
            Log::info('[CancelPilotBilling] Completed', [
                'canceled' => $canceled,
                'errors' => $errors,
            ]);
        }

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Pilot tenants: customer present, no gateway ref, created via the pilot action.
     *
     * @return Collection<int, Tenant>
     */
    private function pilotTenants(): Collection
    {
        return Tenant::with('customer')
            ->whereHas('customer', function ($query): void {
                $query->whereDoesntHave('gatewayRefs')
                    ->where('metadata->created_via', self::PILOT_CREATED_VIA);
            })
            ->get();
    }

    /**
     * Resolve a single tenant by slug. Returns null (and reports) if not found.
     *
     * @return Collection<int, Tenant>|null
     */
    private function singleTenant(string $slug): ?Collection
    {
        $tenant = Tenant::with('customer')->where('slug', $slug)->first();
        if ($tenant === null) {
            $this->error("✕ Tenant not found: {$slug}");

            return null;
        }

        return new Collection([$tenant]);
    }
}
