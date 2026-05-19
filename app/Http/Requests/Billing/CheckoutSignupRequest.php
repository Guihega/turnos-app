<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use App\Enums\Billing\BillingInterval;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\LaravelCipherSweet\Rules\EncryptedUniqueRule;

/**
 * CheckoutSignupRequest: validation for POST /checkout (PR-O).
 *
 * Extends OnboardingController's existing rules with two billing fields:
 *   - plan_code: required, must exist in billing_plans (public + active).
 *   - interval: required, must be a valid BillingInterval case.
 *
 * Slug/email/branch_code/etc rules mirror OnboardingController to keep
 * a single source of truth on tenant onboarding validation. If those
 * rules ever diverge, the divergence must be intentional and documented.
 */
final class CheckoutSignupRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Guard middleware already blocks authenticated users at the route level.
        // No additional authorization needed here.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // — Plan selection (new in PR-O) —
            'plan_code' => [
                'required',
                'string',
                Rule::exists('billing_plans', 'code')
                    ->where('is_public', true)
                    ->where('is_active', true),
            ],
            'interval' => [
                'required',
                'string',
                Rule::in(array_column(BillingInterval::cases(), 'value')),
            ],

            // — User account —
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                new EncryptedUniqueRule(User::class, 'email_index'),
            ],
            'password' => ['required', 'confirmed', Password::defaults()],

            // — Tenant / Company —
            'company_name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:63',
                'regex:/^[a-z0-9]([a-z0-9\-]*[a-z0-9])?$/',
                'unique:tenants,slug',
            ],
            'company_phone' => ['nullable', 'string', 'max:20'],
            'company_country' => ['nullable', 'string', 'max:2'],

            // — First branch —
            'branch_name' => ['required', 'string', 'max:255'],
            'branch_code' => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9\-]+$/'],
            'branch_address' => ['nullable', 'string', 'max:500'],
            'branch_city' => ['nullable', 'string', 'max:100'],
            'branch_state' => ['nullable', 'string', 'max:100'],
            'branch_country' => ['nullable', 'string', 'max:2'],
            'branch_timezone' => ['nullable', 'string', 'max:50'],
            'branch_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'branch_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'branch_schedule' => ['nullable', 'array'],
        ];
    }

    /**
     * Resolve the BillingInterval enum from the validated string.
     */
    public function billingInterval(): BillingInterval
    {
        return BillingInterval::from($this->validated('interval'));
    }
}
