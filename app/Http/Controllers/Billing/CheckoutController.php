<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\CreateCustomerAction;
use App\Actions\Billing\CreateSubscriptionAction;
use App\Actions\Onboarding\OnboardTenantAction;
use App\Enums\Billing\BillingInterval;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\CheckoutSignupRequest;
use App\Models\Billing\Plan;
use App\Models\Billing\Price;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CheckoutController: public signup flow with billing integration (PR-O).
 *
 * Orchestrates three Actions inside a single DB transaction:
 *   1. OnboardTenantAction        — creates tenant + admin user + first branch.
 *   2. CreateCustomerAction       — registers customer in Stripe.
 *   3. CreateSubscriptionAction   — creates trialing subscription on chosen plan.
 *
 * Atomicity: outer DB::transaction wraps all three. OnboardTenantAction's
 * internal transaction degrades to a savepoint (standard Laravel behavior).
 * If Stripe fails, full rollback prevents orphan tenants.
 *
 * Exceptions (PriceNotFoundException, CustomerNotRegisteredInGatewayException,
 * Stripe API errors) propagate to the framework handler. No defensive catches:
 * silent failure here would mask catalog/config drift.
 */
final class CheckoutController extends Controller
{
    /**
     * GET /checkout — public plan selection page.
     *
     * Lists public + active plans with their MXN prices for the monthly/yearly
     * toggle. Currency is hardcoded to MXN: the public checkout assumes
     * Spanish/MX locale (no country selector in scope for PR-O).
     */
    public function select(): Response
    {
        $plans = Plan::query()
            ->public()
            ->active()
            ->orderBy('sort_order')
            ->with(['prices' => function ($query): void {
                $query->where('is_active', true)
                    ->where('currency', 'MXN');
            }])
            ->get()
            ->map(fn (Plan $plan): array => [
                'code' => $plan->code,
                'name' => $plan->name,
                'description' => $plan->description,
                'prices' => $plan->prices
                    // @phpstan-ignore-next-line argument.type
                    ->map(static fn (Price $price): array => [
                        'currency' => $price->currency,
                        'interval' => $price->interval->value,
                        'interval_count' => $price->interval_count,
                        'amount_cents' => $price->amount_cents,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        return Inertia::render('Billing/Checkout/Select', [
            'plans' => $plans,
        ]);
    }

    /**
     * GET /checkout/signup — render signup form with pre-selected plan + interval.
     *
     * Validates query params against the same rules as Select to fail loud on
     * direct visits with bogus values (e.g. /checkout/signup?plan=fake).
     */
    public function signupForm(Request $request): Response|RedirectResponse
    {
        $planCode = (string) $request->query('plan', '');
        $interval = (string) $request->query('interval', '');

        $plan = Plan::query()
            ->public()
            ->active()
            ->where('code', $planCode)
            ->first();

        $validIntervals = array_column(BillingInterval::cases(), 'value');

        if ($plan === null || ! in_array($interval, $validIntervals, true)) {
            return redirect()->route('checkout.select');
        }

        /** @var Price|null $price */
        $price = $plan->prices()
            ->where('is_active', true)
            ->where('currency', 'MXN')
            ->where('interval', $interval)
            ->first();

        if ($price === null) {
            return redirect()->route('checkout.select');
        }

        return Inertia::render('Billing/Checkout/Signup', [
            'plan' => [
                'code' => $plan->code,
                'name' => $plan->name,
                'description' => $plan->description,
            ],
            'price' => [
                'currency' => $price->currency,
                'interval' => $price->interval->value,
                'amount_cents' => $price->amount_cents,
            ],
        ]);
    }

    /**
     * POST /checkout — process signup + billing in a single transaction.
     */
    public function signup(
        CheckoutSignupRequest $request,
        OnboardTenantAction $onboardTenant,
        CreateCustomerAction $createCustomer,
        CreateSubscriptionAction $createSubscription,
    ): RedirectResponse {
        $validated = $request->validated();
        $interval = $request->billingInterval();

        $result = DB::transaction(function () use (
            $validated,
            $interval,
            $onboardTenant,
            $createCustomer,
            $createSubscription,
        ): array {
            $onboarded = $onboardTenant->execute($validated);

            $plan = Plan::query()
                ->where('code', $validated['plan_code'])
                ->firstOrFail();

            $customer = $createCustomer->execute($onboarded['tenant'], [
                'billing_email' => $validated['email'],
                'billing_name' => $validated['company_name'],
                'country' => $validated['company_country'] ?? 'MX',
            ]);

            $subscription = $createSubscription->execute($customer, $plan, $interval);

            return [
                'tenant' => $onboarded['tenant'],
                'user' => $onboarded['user'],
                'plan' => $plan,
                'subscription' => $subscription,
            ];
        });

        event(new Registered($result['user']));
        Auth::login($result['user']);

        Log::info('checkout.signup.success', [
            'tenant_id' => $result['tenant']->id,
            'plan_code' => $result['plan']->code,
            'subscription_id' => $result['subscription']->id,
            'interval' => $interval->value,
        ]);

        return redirect()
            ->route('checkout.confirmation')
            ->with('checkout', [
                'tenant_name' => $result['tenant']->name,
                'tenant_slug' => $result['tenant']->slug,
                'plan_name' => $result['plan']->name,
                'interval' => $interval->value,
                'trial_ends_at' => $result['subscription']->trial_ends_at?->toIso8601String(),
            ]);
    }

    /**
     * GET /checkout/confirmation — post-signup success page.
     *
     * Reads flash data from the signup redirect. Direct visits (refresh or
     * deep-link) without flash redirect to dashboard since there is nothing
     * meaningful to display.
     */
    public function confirmation(): Response|RedirectResponse
    {
        $data = session('checkout');

        if (! is_array($data)) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Billing/Checkout/Confirmation', [
            'tenantName' => $data['tenant_name'] ?? null,
            'tenantSlug' => $data['tenant_slug'] ?? null,
            'planName' => $data['plan_name'] ?? null,
            'interval' => $data['interval'] ?? null,
            'trialEndsAt' => $data['trial_ends_at'] ?? null,
        ]);
    }
}
