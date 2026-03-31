<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransferTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('tickets.transfer') ?? false;
    }

    public function rules(): array
    {
        return [
            'target_queue_id' => ['required', 'ulid', 'exists:queues,id'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'target_queue_id.required' => 'La cola destino es obligatoria.',
            'target_queue_id.exists' => 'La cola destino no existe.',
        ];
    }
}
