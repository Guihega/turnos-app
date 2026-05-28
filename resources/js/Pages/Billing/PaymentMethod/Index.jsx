import { useMemo, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { Elements } from '@stripe/react-stripe-js';
import { loadStripe } from '@stripe/stripe-js';

import StripePaymentElement from '../Stripe/PaymentElement.jsx';

// ─── Design Tokens (mirror Onboarding/Register.jsx + Checkout/*) ───
const T = {
    blue:    '#2563eb',
    blueLt:  '#dbeafe',
    green:   '#16a34a',
    red:     '#dc2626',
    gray100: '#f3f4f6',
    gray200: '#e5e7eb',
    gray500: '#6b7280',
    gray600: '#4b5563',
    gray700: '#374151',
    gray900: '#111827',
    card:    '#ffffff',
    bg:      '#f8fafc',
    sans:    "'Inter', system-ui, -apple-system, sans-serif",
    radius:  '12px',
    shadow:  '0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.06)',
    shadowMd:'0 4px 6px rgba(0,0,0,0.07), 0 2px 4px rgba(0,0,0,0.06)',
};

const BRAND_LABEL = {
    visa: 'Visa',
    mastercard: 'Mastercard',
    amex: 'American Express',
    discover: 'Discover',
    diners: 'Diners',
    jcb: 'JCB',
    unionpay: 'UnionPay',
};

function brandLabel(brand) {
    if (!brand) return 'Tarjeta';
    return BRAND_LABEL[brand.toLowerCase()] ?? brand;
}

function formatExpiry(month, year) {
    if (!month || !year) return null;
    const m = String(month).padStart(2, '0');
    const y = String(year).slice(-2);
    return `${m}/${y}`;
}

/**
 * Billing/PaymentMethod/Index — gateway-agnostic shell for managing
 * the tenant's default payment method (PR-AA, per ADR-018).
 *
 * Receives a SetupIntent client_secret from the server and hands it to
 * the gateway-specific component (Stripe Elements for now; in Fase 4
 * this same shell can branch to MercadoPago Brick based on the
 * resolved gateway).
 *
 * On successful collection, the gateway component yields a tokenized
 * paymentMethodId. We POST that to the backend; the Action attaches it
 * to the gateway customer and persists the local mirror.
 *
 * Props:
 *   stripePublicKey:         string
 *   setupIntentClientSecret: string
 *   currentPaymentMethod:    { brand, last4, exp_month, exp_year } | null
 */
export default function Index({ stripePublicKey, setupIntentClientSecret, currentPaymentMethod = null }) {
    const { props: pageProps } = usePage();
    const flashSuccess = pageProps?.flash?.success ?? null;

    const [submitError, setSubmitError] = useState(null);

    // loadStripe returns a Promise; memoize so we don't reload Stripe.js
    // on every re-render. The publishable key is safe to expose client-side.
    const stripePromise = useMemo(
        () => loadStripe(stripePublicKey),
        [stripePublicKey],
    );

    const elementsOptions = useMemo(
        () => ({
            clientSecret: setupIntentClientSecret,
            appearance: {
                theme: 'stripe',
                variables: {
                    colorPrimary: T.blue,
                    fontFamily: T.sans,
                    borderRadius: '8px',
                },
            },
        }),
        [setupIntentClientSecret],
    );

    const handleSuccess = (paymentMethodId) => {
        setSubmitError(null);
        router.post(
            '/administracion/metodo-de-pago',
            {
                payment_method_id: paymentMethodId,
                set_as_default: true,
            },
            {
                onError: (errors) => {
                    const first = Object.values(errors)[0];
                    setSubmitError(typeof first === 'string' ? first : 'No pudimos guardar la tarjeta.');
                },
            },
        );
    };

    const handleError = (message) => {
        setSubmitError(message);
    };

    const expiry = currentPaymentMethod
        ? formatExpiry(currentPaymentMethod.exp_month, currentPaymentMethod.exp_year)
        : null;

    return (
        <>
            <Head title="Método de pago — Olinora" />

            <div style={{
                minHeight: '100vh',
                background: T.bg,
                padding: '32px 16px',
                fontFamily: T.sans,
            }}>
                <div style={{ maxWidth: 560, margin: '0 auto' }}>

                    <h1 style={{
                        fontSize: 24, fontWeight: 800, color: T.gray900,
                        margin: 0, marginBottom: 4,
                    }}>
                        Método de pago
                    </h1>
                    <p style={{
                        color: T.gray600, fontSize: 14, margin: 0, marginBottom: 24,
                    }}>
                        Guardá la tarjeta con la que se cobrarán tus suscripciones y facturas.
                    </p>

                    {flashSuccess && (
                        <div style={{
                            padding: '12px 14px',
                            background: '#f0fdf4',
                            border: `1px solid ${T.green}`,
                            borderRadius: 8,
                            color: T.green,
                            fontSize: 14,
                            marginBottom: 16,
                        }}>
                            {flashSuccess}
                        </div>
                    )}

                    {currentPaymentMethod && (
                        <div style={{
                            background: T.card,
                            border: `1px solid ${T.gray200}`,
                            borderRadius: T.radius,
                            padding: 16,
                            marginBottom: 24,
                            boxShadow: T.shadow,
                        }}>
                            <div style={{ fontSize: 12, color: T.gray500, fontWeight: 600, marginBottom: 6 }}>
                                TARJETA ACTUAL
                            </div>
                            <div style={{ fontSize: 16, fontWeight: 700, color: T.gray900 }}>
                                {brandLabel(currentPaymentMethod.brand)} ····{currentPaymentMethod.last4 ?? '----'}
                                {expiry && (
                                    <span style={{ color: T.gray500, fontWeight: 500, marginLeft: 10, fontSize: 14 }}>
                                        Vence {expiry}
                                    </span>
                                )}
                            </div>
                            <div style={{ fontSize: 13, color: T.gray500, marginTop: 8 }}>
                                Si guardás una nueva, reemplazará a esta como la tarjeta predeterminada.
                            </div>
                        </div>
                    )}

                    <div style={{
                        background: T.card,
                        border: `1px solid ${T.gray200}`,
                        borderRadius: T.radius,
                        padding: 24,
                        boxShadow: T.shadowMd,
                    }}>
                        <h2 style={{
                            fontSize: 16, fontWeight: 700, color: T.gray900,
                            margin: 0, marginBottom: 16,
                        }}>
                            {currentPaymentMethod ? 'Reemplazar tarjeta' : 'Agregar tarjeta'}
                        </h2>

                        {submitError && (
                            <div style={{
                                padding: '10px 12px',
                                background: '#fef2f2',
                                border: `1px solid ${T.red}`,
                                borderRadius: 8,
                                color: T.red,
                                fontSize: 13,
                                marginBottom: 16,
                            }}>
                                {submitError}
                            </div>
                        )}

                        <Elements stripe={stripePromise} options={elementsOptions}>
                            <StripePaymentElement
                                onSuccess={handleSuccess}
                                onError={handleError}
                            />
                        </Elements>

                        <p style={{
                            fontSize: 12, color: T.gray500, marginTop: 16, marginBottom: 0, textAlign: 'center',
                        }}>
                            Los datos de la tarjeta se procesan con Stripe. No los guardamos en nuestros servidores.
                        </p>
                    </div>

                </div>
            </div>
        </>
    );
}
