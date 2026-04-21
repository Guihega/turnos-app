<?php

namespace App\Http\Requests;

use App\Models\Lead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeadRequest extends FormRequest
{
    /**
     * El formulario de captura es público. No requiere autorización.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc,dns', 'max:180'],
            'organization' => ['required', 'string', 'min:2', 'max:160'],
            'sector' => ['required', Rule::in(Lead::SECTORS)],
            'size' => ['required', Rule::in(Lead::SIZES)],
            'message' => ['nullable', 'string', 'max:500'],

            // UTM parameters — si vienen, validar; si no, pasan como null.
            'utm_source' => ['nullable', 'string', 'max:255'],
            'utm_medium' => ['nullable', 'string', 'max:255'],
            'utm_campaign' => ['nullable', 'string', 'max:255'],
            'utm_term' => ['nullable', 'string', 'max:255'],
            'utm_content' => ['nullable', 'string', 'max:255'],

            // Honeypot — debe venir vacío. Si un bot lo llena, rechazamos.
            'website' => ['nullable', 'string', 'max:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Por favor indique su nombre.',
            'name.min' => 'El nombre parece demasiado corto.',
            'email.required' => 'Necesitamos un correo para responderle.',
            'email.email' => 'El correo no parece válido.',
            'organization.required' => 'Indique el nombre de su organización.',
            'sector.required' => 'Seleccione un sector.',
            'sector.in' => 'El sector seleccionado no es válido.',
            'size.required' => 'Seleccione el tamaño de su operación.',
            'size.in' => 'La opción de tamaño no es válida.',
            'message.max' => 'El mensaje no puede exceder 500 caracteres.',
            'website.max' => 'Error de validación.', // genérico a propósito
        ];
    }

    /**
     * Datos limpios listos para persistir (sin el honeypot).
     *
     * Los UTMs vienen del form (useForm en Welcome.jsx los captura de la URL).
     * La metadata del request (ip/ua/referrer) la extrae el backend.
     */
    public function cleanData(): array
    {
        $validated = collect($this->validated())->except(['website']);

        // Normalizar UTMs vacíos o whitespace a null (mejor para filtros SQL).
        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $utm) {
            if ($validated->has($utm) && trim((string) $validated->get($utm)) === '') {
                $validated->put($utm, null);
            }
        }

        return $validated
            ->merge([
                'ip' => $this->ip(),
                'user_agent' => substr((string) $this->userAgent(), 0, 512),
                'referrer' => substr((string) $this->header('referer', ''), 0, 512) ?: null,
                'status' => 'new',
            ])
            ->toArray();
    }
}
