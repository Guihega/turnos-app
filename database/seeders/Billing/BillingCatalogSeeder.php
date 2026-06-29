<?php

declare(strict_types=1);

namespace Database\Seeders\Billing;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates the billing catalog seeding in dependency order:
 *
 *   1. Features  (no FK deps)
 *   2. Plans     (creates plan rows + plan_features that depend on Features)
 *   3. Prices    (depends on Plans)
 *
 * Wrapped in a transaction so partial seeds never reach production. If any
 * inner seeder throws, the whole catalog rolls back and stays consistent.
 *
 * Safe to re-run on every deploy: every inner seeder uses updateOrCreate
 * on stable natural keys (Feature.code, Plan.code, Price unique tuple).
 */
final class BillingCatalogSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->call([
                FeaturesSeeder::class,
                PlansSeeder::class,
                PricesSeeder::class,
            ]);
        });
    }
}
