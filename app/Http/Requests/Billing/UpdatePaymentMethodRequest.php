<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;

/**
 * UpdatePaymentMethodRequest: validation for POST /administracion/metodo-de-pago (PR-AA).
 *
 * The frontend (Stripe Elements per ADR-018) confirms a SetupIntent
 * and produces a PaymentMethod token of the shape `pm_<alphanumeric>`.
 * This request validates that token shape and the optional
 * set-as-default flag.
 *
 * Authorization: route-level middleware already enforces auth +
 * verified + tenant.scope + role:admin. No additional authorization
 * is performed here.
 */
final class UpdatePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // Stripe PaymentMethod ids are 'pm_' + base62-ish characters.
            // We accept conservatively a-zA-Z0-9 + underscore, length 27..255
            // (Stripe ids vary in length but never exceed 255). The exact
            // shape is validated again by the gateway adapter on attach,
            // so this is just a fast-fail guard.
            'payment_method_id' => [
                'required',
                'string',
                'regex:/^pm_[a-zA-Z0-9_]{6,}$/',
                'max:255',
            ],
            'set_as_default' => ['nullable', 'boolean'],
        ];
    }

    public function setAsDefault(): bool
    {
        // Default to TRUE if the field is absent: in the typical UX
        // ("update card on file") the user expects the new card to
        // become the default, and explicitly opting out is rare.
        $raw = $this->validated('set_as_default');

        return $raw === null ? true : (bool) $raw;
    }

    public function paymentMethodId(): string
    {
        return (string) $this->validated('payment_method_id');
    }
}
