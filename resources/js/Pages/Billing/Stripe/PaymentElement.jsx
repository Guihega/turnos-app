import { useState } from 'react';
import { PaymentElement, useStripe, useElements } from '@stripe/react-stripe-js';

// ─── Design Tokens (mirror Onboarding/Register.jsx + Checkout/*) ───
const T = {
    blue:    '#2563eb',
    blueDk:  '#1d4ed8',
    red:     '#dc2626',
    gray300: '#d1d5db',
    gray400: '#9ca3af',
    gray500: '#6b7280',
    gray700: '#374151',
    card:    '#ffffff',
    sans:    "'Inter', system-ui, -apple-system, sans-serif",
};

/**
 * StripePaymentElement.jsx — Stripe-specific component for collecting a
 * payment method via Stripe Elements (PR-AA, per ADR-018 §2).
 *
 * MUST be rendered inside a <Elements stripe={...} options={{clientSecret}}>
 * provider. The provider lives in the gateway-agnostic shell page
 * (Pages/Billing/PaymentMethod/Index.jsx).
 *
 * When the user submits, this component calls stripe.confirmSetup(),
 * which validates the entered card with Stripe directly (no PAN ever
 * touches our server) and produces a tokenized PaymentMethod id. That
 * id is then handed to onSuccess(paymentMethodId) — the parent is
 * responsible for posting it to our backend (POST /administracion/metodo-de-pago).
 *
 * This file is the sibling reference for MercadoPago Brick.jsx in Fase 4.
 *
 * Props:
 *   onSuccess: (paymentMethodId: string) => void
 *   onError:   (message: string) => void
 */
export default function StripePaymentElement({ onSuccess, onError }) {
    const stripe = useStripe();
    const elements = useElements();
    const [submitting, setSubmitting] = useState(false);
    const [errorMessage, setErrorMessage] = useState(null);

    const handleSubmit = async (e) => {
        e.preventDefault();

        if (!stripe || !elements) {
            // Stripe.js still loading; ignore submit until ready.
            return;
        }

        setSubmitting(true);
        setErrorMessage(null);

        // Confirm the SetupIntent. Using redirect: 'if_required' keeps the
        // flow on-page for 3DS-light cards and only redirects when the
        // issuer demands a full challenge — matches the UX expectation of
        // "update card on file" being a quick action.
        const { error, setupIntent } = await stripe.confirmSetup({
            elements,
            redirect: 'if_required',
        });

        if (error) {
            const msg = error.message ?? 'No pudimos procesar la tarjeta. Intentá de nuevo.';
            setErrorMessage(msg);
            onError?.(msg);
            setSubmitting(false);
            return;
        }

        const paymentMethodId = typeof setupIntent?.payment_method === 'string'
            ? setupIntent.payment_method
            : null;

        if (!paymentMethodId) {
            const msg = 'La tarjeta se procesó pero no recibimos un identificador. Recargá e intentá de nuevo.';
            setErrorMessage(msg);
            onError?.(msg);
            setSubmitting(false);
            return;
        }

        onSuccess(paymentMethodId);
        // We intentionally do NOT reset submitting here: the parent will
        // navigate away (Inertia router.post → redirect), and clearing
        // submitting briefly would cause the button to flicker as enabled
        // for one frame.
    };

    return (
        <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
            <PaymentElement
                options={{
                    layout: 'tabs',
                }}
            />

            {errorMessage && (
                <div style={{
                    padding: '10px 12px',
                    background: '#fef2f2',
                    border: `1px solid ${T.red}`,
                    borderRadius: 8,
                    color: T.red,
                    fontSize: 13,
                    fontFamily: T.sans,
                }}>
                    {errorMessage}
                </div>
            )}

            <button
                type="submit"
                disabled={!stripe || !elements || submitting}
                style={{
                    width: '100%',
                    padding: '12px 16px',
                    background: (!stripe || !elements || submitting) ? T.gray400 : T.blue,
                    color: '#fff',
                    border: 'none',
                    borderRadius: 8,
                    fontFamily: T.sans,
                    fontWeight: 700,
                    fontSize: 15,
                    cursor: (!stripe || !elements || submitting) ? 'not-allowed' : 'pointer',
                    transition: 'background 0.15s',
                }}
            >
                {submitting ? 'Guardando…' : 'Guardar tarjeta'}
            </button>
        </form>
    );
}
