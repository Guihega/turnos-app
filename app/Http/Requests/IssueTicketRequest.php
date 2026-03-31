<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\TicketPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IssueTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('tickets.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'ulid', 'exists:branches,id'],
            'queue_id' => ['required', 'ulid', 'exists:queues,id'],
            'service_id' => ['required', 'ulid', 'exists:services,id'],
            'appointment_id' => ['nullable', 'ulid', 'exists:appointments,id'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:20'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_id_number' => ['nullable', 'string', 'max:30'],
            'priority' => ['nullable', Rule::enum(TicketPriority::class)],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'branch_id.required' => 'La sucursal es obligatoria.',
            'queue_id.required' => 'La cola es obligatoria.',
            'service_id.required' => 'El servicio es obligatorio.',
            'branch_id.exists' => 'La sucursal no existe.',
            'queue_id.exists' => 'La cola no existe.',
            'service_id.exists' => 'El servicio no existe.',
        ];
    }
}
