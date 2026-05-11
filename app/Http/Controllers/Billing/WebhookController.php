<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Billing\Contracts\BillingGateway;
use App\Billing\Exceptions\GatewaySignatureException;
use App\Http\Controllers\Controller;
use App\Jobs\Billing\ProcessBillingWebhookEvent;
use App\Models\Billing\WebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * POST /billing/webhook — public endpoint that receives Stripe webhook
 * deliveries.
 *
 * Auth model: NO Sanctum, NO session, NO CSRF. The cryptographic
 * signature verified by BillingGateway::verifyWebhookSignature is the
 * authentication mechanism (ADR-007).
 *
 * Flow:
 *   1. Read raw payload + Stripe-Signature header.
 *   2. Verify signature; reject with 400 if invalid (event is NOT
 *      persisted — we cannot trust it is real Stripe).
 *   3. Persist to billing_webhook_events. The UNIQUE(gateway,
 *      gateway_event_id) constraint guarantees idempotency at the
 *      DB level: a duplicate delivery returns 200 without dispatching
 *      a second job.
 *   4. Dispatch ProcessBillingWebhookEvent to the queue.
 *   5. Return 200 immediately. Stripe retries on non-2xx or >30s
 *     response (ADR-012).
 */
final class WebhookController extends Controller
{
    public function __invoke(Request $request, BillingGateway $gateway): JsonResponse
    {
        $payload = $request->getContent();
        $signature = (string) $request->header('Stripe-Signature', '');

        if ($payload === '' || $signature === '') {
            return response()->json([
                'error' => 'invalid_payload',
                'message' => 'Missing payload or Stripe-Signature header.',
            ], 400);
        }

        // Step 1: verify signature. Throws GatewaySignatureException on bad sig.
        try {
            $event = $gateway->verifyWebhookSignature($payload, $signature);
        } catch (GatewaySignatureException $e) {
            Log::warning('billing.webhook.signature_invalid', [
                'gateway' => 'stripe',
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'invalid_signature',
            ], 400);
        }

        $eventId = isset($event['id']) && is_string($event['id']) ? $event['id'] : null;
        $eventType = isset($event['type']) && is_string($event['type']) ? $event['type'] : null;
        if ($eventId === null || $eventType === null) {
            return response()->json([
                'error' => 'invalid_payload',
                'message' => 'Verified event is missing id or type.',
            ], 400);
        }

        // Step 2: persist (or detect duplicate via UNIQUE constraint).
        try {
            $webhookEvent = WebhookEvent::firstOrCreate(
                [
                    'gateway' => 'stripe',
                    'gateway_event_id' => $eventId,
                ],
                [
                    'event_type' => $eventType,
                    'payload' => $event,
                    'signature_header' => $signature,
                ]
            );
        } catch (Throwable $e) {
            Log::error('billing.webhook.persist_failed', [
                'gateway' => 'stripe',
                'gateway_event_id' => $eventId,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'persist_failed',
            ], 500);
        }

        // Step 3: dispatch only if this is the first time we see this event.
        // wasRecentlyCreated is true only on a fresh INSERT.
        if ($webhookEvent->wasRecentlyCreated) {
            ProcessBillingWebhookEvent::dispatch($webhookEvent->id);
        } else {
            Log::info('billing.webhook.duplicate_ignored', [
                'gateway' => 'stripe',
                'gateway_event_id' => $eventId,
            ]);
        }

        return response()->json(['received' => true], 200);
    }
}
