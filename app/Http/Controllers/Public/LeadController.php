<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeadRequest;
use App\Mail\NewLeadNotification;
use App\Models\Lead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class LeadController extends Controller
{
    /**
     * Persiste el lead y dispara la notificación.
     *
     * El envío del mail NO es bloqueante: si falla Resend, el lead
     * queda guardado y se loguea el error. La integridad del dato
     * está arriba de la entrega del mail.
     */
    public function store(StoreLeadRequest $request): RedirectResponse
    {
        $lead = Lead::create($request->cleanData());

        try {
            Mail::to($this->notificationAddress())->send(new NewLeadNotification($lead));
        } catch (\Throwable $e) {
            Log::warning('Lead guardado pero falló notificación por mail', [
                'lead_id' => $lead->id,
                'error' => $e->getMessage(),
            ]);
        }

        return back()
            ->with('success', 'Su solicitud fue recibida.')
            ->with('leadSubmitted', true);
    }

    /**
     * Resuelve la dirección donde deben llegar las notificaciones de leads.
     *
     * Prioridad:
     *   1. MAIL_LEADS_NOTIFICATION_TO (configurable en .env)
     *   2. MAIL_FROM_ADDRESS (fallback razonable)
     *   3. String vacío que Mail::to() rechazará con excepción controlada
     */
    protected function notificationAddress(): string
    {
        $configured = config('mail.leads_notification_to');
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $from = config('mail.from');
        if (is_array($from) && ! empty($from['address'])) {
            return $from['address'];
        }

        if (is_string($from) && $from !== '') {
            return $from;
        }

        // Último recurso: env directo
        return (string) env('MAIL_FROM_ADDRESS', '');
    }
}
