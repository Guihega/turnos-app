<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\CreateSetupIntentAction;
use App\Actions\Billing\UpdatePaymentMethodAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\UpdatePaymentMethodRequest;
use App\Models\Billing\Customer;
use App\Models\Billing\PaymentMethod;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * PaymentMethodController: tenant-scoped UI to view + update the
 * Customer's default payment method (PR-AA).
 *
 * The page implements ADR-018's gateway-agnostic shell: this
 * controller hands a SetupIntent's client_secret + the Stripe
 * publishable key to the React component, which renders Stripe
 * Elements (Pages/Billing/Stripe/PaymentElement.jsx). When MercadoPago
 * lands in Fase 4, the same page can branch on the resolved gateway
 * and render Brick.jsx instead.
 *
 * Authorization: route-level middleware enforces auth + verified +
 * tenant.scope + role:admin + 2FA-for-admins. No additional
 * authorization here.
 *
 * If the Tenant has no billing Customer yet (legacy pilots created
 * before billing landed), we 404 — there's nothing to manage and the
 * fix is operational (run the backfill, not patch this page).
 */
final class PaymentMethodController extends Controller
{
    /**
     * GET /administracion/metodo-de-pago.
     *
     * Mints (or reuses, via idempotency) a SetupIntent so the
     * frontend can render Stripe Elements immediately. The local
     * `payment_methods` table provides the "card on file" summary
     * without a round trip to Stripe — the local mirror is
     * authoritative for display.
     */
    public function show(Request $request, CreateSetupIntentAction $createSetupIntent): Response
    {
        $customer = $this->resolveCustomer($request);

        $setupIntent = $createSetupIntent->execute($customer);

        /** @var PaymentMethod|null $currentDefault */
        $currentDefault = PaymentMethod::query()
            ->where('customer_id', $customer->id)
            ->where('is_default', true)
            ->first();

        return Inertia::render('Billing/PaymentMethod/Index', [
            'stripePublicKey' => (string) config('billing.gateways.stripe.public_key'),
            'setupIntentClientSecret' => $setupIntent->clientSecret,
            'currentPaymentMethod' => $currentDefault === null ? null : [
                'brand' => $currentDefault->brand,
                'last4' => $currentDefault->last4,
                'exp_month' => $currentDefault->exp_month,
                'exp_year' => $currentDefault->exp_year,
            ],
        ]);
    }

    /**
     * POST /administracion/metodo-de-pago.
     *
     * Takes the PaymentMethod id minted by the frontend SDK after
     * confirming the SetupIntent and attaches it to the gateway
     * customer + persists the local mirror.
     */
    public function store(
        UpdatePaymentMethodRequest $request,
        UpdatePaymentMethodAction $updatePaymentMethod,
    ): RedirectResponse {
        $customer = $this->resolveCustomer($request);

        $paymentMethod = $updatePaymentMethod->execute(
            customer: $customer,
            paymentMethodId: $request->paymentMethodId(),
            setAsDefault: $request->setAsDefault(),
        );

        Log::info('payment_method.updated', [
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
            'payment_method_id' => $paymentMethod->id,
            'brand' => $paymentMethod->brand,
            'last4' => $paymentMethod->last4,
            'set_as_default' => $request->setAsDefault(),
        ]);

        return redirect()
            ->route('admin.payment-method.show')
            ->with('success', 'Tu método de pago se actualizó correctamente.');
    }

    private function resolveCustomer(Request $request): Customer
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        /** @var Tenant|null $tenant */
        $tenant = $user->tenant;
        if ($tenant === null) {
            abort(404, 'El usuario no pertenece a ningún tenant.');
        }

        $customer = $tenant->customer;

        if (! $customer instanceof Customer) {
            abort(404, 'Este tenant aún no tiene un Customer de billing. Ejecuta el backfill primero.');
        }

        return $customer;
    }
}
