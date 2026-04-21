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
     */
    public function cleanData(): array
    {
        return collect($this->validated())
            ->except('website')
            ->merge([
                'ip' => $this->ip(),
                'user_agent' => substr((string) $this->userAgent(), 0, 512),
                'referrer' => substr((string) $this->header('referer', ''), 0, 512) ?: null,
                'status' => 'new',
            ])
            ->toArray();
    }
}
