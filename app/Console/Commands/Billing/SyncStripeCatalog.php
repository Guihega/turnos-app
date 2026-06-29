<?php

declare(strict_types=1);

namespace App\Console\Commands\Billing;

use App\Billing\Stripe\StripeClientFactory;
use App\Models\Billing\Plan;
use App\Models\Billing\Price;
use Illuminate\Console\Command;
use Stripe\StripeClient;
use Throwable;

final class SyncStripeCatalog extends Command
{
    protected $signature = 'billing:sync-stripe-catalog {--dry-run : Muestra que haria sin crear nada en Stripe ni escribir en la BD}';

    protected $description = 'Crea los Products y Prices del catalogo local en Stripe y vincula los gateway_refs.';

    private const PLAN_META_KEY = 'olinora_plan_code';

    private const PRICE_META_KEY = 'olinora_price_id';

    private int $created = 0;

    private int $skipped = 0;

    private int $failed = 0;

    public function handle(StripeClientFactory $factory): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('-- MODO DRY-RUN: no se creara nada en Stripe ni se escribira en la BD --');
        }

        try {
            $stripe = $factory->make();
        } catch (Throwable $e) {
            $this->error('No se pudo inicializar el cliente de Stripe: '.$e->getMessage());

            return self::FAILURE;
        }

        $mode = config('billing.gateways.stripe.mode');
        $this->info('Modo Stripe activo: '.$mode);

        if ($mode === 'live' && ! $dryRun) {
            if (! $this->confirm('Estas en modo LIVE. Esto creara productos/precios reales en Stripe. Continuar?', false)) {
                $this->warn('Cancelado por el usuario.');

                return self::SUCCESS;
            }
        }

        $plans = Plan::query()
            ->with(['prices' => fn ($q) => $q->where('amount_cents', '>', 0)])
            ->whereHas('prices', fn ($q) => $q->where('amount_cents', '>', 0))
            ->orderBy('sort_order')
            ->get();

        if ($plans->isEmpty()) {
            $this->warn('No hay planes con precios > 0 para sincronizar.');

            return self::SUCCESS;
        }

        foreach ($plans as $plan) {
            $this->line('');
            $this->info('Plan: '.$plan->name.' ('.$plan->code.')');

            try {
                $productId = $this->ensureProduct($stripe, $plan, $dryRun);
            } catch (Throwable $e) {
                $this->failed++;
                $this->error('  x No se pudo crear/encontrar el Product del plan '.$plan->code.': '.$e->getMessage());

                continue;
            }

            foreach ($plan->prices as $price) {
                /** @var Price $price */
                $this->syncPrice($stripe, $plan, $price, $productId, $dryRun);
            }
        }

        $this->renderSummary($dryRun);

        return $this->failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function ensureProduct(StripeClient $stripe, Plan $plan, bool $dryRun): string
    {
        $existingFromMeta = $plan->metadata['stripe_product_id'] ?? null;
        if (is_string($existingFromMeta) && $existingFromMeta !== '') {
            $this->line('  - Product existente (metadata): '.$existingFromMeta);

            return $existingFromMeta;
        }

        if (! $dryRun) {
            $search = $stripe->products->search([
                'query' => sprintf("metadata['%s']:'%s'", self::PLAN_META_KEY, $plan->code),
                'limit' => 1,
            ]);

            if (! empty($search->data)) {
                $found = $search->data[0]->id;
                $this->line('  - Product encontrado en Stripe: '.$found);
                $this->persistProductId($plan, $found);

                return $found;
            }
        }

        if ($dryRun) {
            $this->line('  + [dry-run] crearia Product '.$plan->name);

            return 'prod_DRYRUN_'.$plan->code;
        }

        $product = $stripe->products->create([
            'name' => $plan->name,
            'description' => $plan->description ?: null,
            'metadata' => [self::PLAN_META_KEY => $plan->code],
        ]);

        $this->line('  + Product creado: '.$product->id);
        $this->persistProductId($plan, $product->id);

        return $product->id;
    }

    private function syncPrice(StripeClient $stripe, Plan $plan, Price $price, string $productId, bool $dryRun): void
    {
        $refs = $price->gateway_refs ?? [];
        $label = $price->currency.'/'.$price->interval->value.'/'.number_format($price->amount_cents / 100, 2);

        if (! empty($refs['stripe'])) {
            $this->skipped++;
            $this->line('    = '.$label.' ya vinculado ('.$refs['stripe'].')');

            return;
        }

        if ($dryRun) {
            $this->created++;
            $this->line('    + [dry-run] crearia Price '.$label);

            return;
        }

        try {
            $stripePrice = $stripe->prices->create([
                'product' => $productId,
                'currency' => strtolower($price->currency),
                'unit_amount' => $price->amount_cents,
                'recurring' => [
                    'interval' => $this->mapInterval($price->interval->value),
                    'interval_count' => $price->interval_count,
                ],
                'metadata' => [
                    self::PRICE_META_KEY => $price->id,
                    'plan_code' => $plan->code,
                ],
            ], [
                'idempotency_key' => 'olinora_price_'.$price->id,
            ]);

            $refs['stripe'] = $stripePrice->id;
            $price->gateway_refs = $refs;
            $price->save();

            $this->created++;
            $this->line('    + Price creado: '.$label.' -> '.$stripePrice->id);
        } catch (Throwable $e) {
            $this->failed++;
            $this->error('    x Fallo '.$label.': '.$e->getMessage());
        }
    }

    private function persistProductId(Plan $plan, string $productId): void
    {
        $meta = $plan->metadata ?? [];
        $meta['stripe_product_id'] = $productId;
        $plan->metadata = $meta;
        $plan->save();
    }

    private function mapInterval(string $interval): string
    {
        return match ($interval) {
            'month' => 'month',
            'year' => 'year',
            'week' => 'week',
            'day' => 'day',
            default => $interval,
        };
    }

    private function renderSummary(bool $dryRun): void
    {
        $this->line('');
        $this->line('------------------------------------');
        $prefix = $dryRun ? '[dry-run] ' : '';
        $this->info($prefix.'Creados:  '.$this->created);
        $this->info($prefix.'Saltados: '.$this->skipped);

        if ($this->failed > 0) {
            $this->error('Fallidos: '.$this->failed);
        } else {
            $this->info('Fallidos: 0');
        }
        $this->line('------------------------------------');
    }
}
