<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'customer_note' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'delivery_region_id' => [
                'required',
                Rule::exists('delivery_regions', 'id')->where('is_active', true),
            ],
            'idempotency_token' => ['required', 'string', 'max:100'],
            'terms_agreed' => ['accepted'],
            'shipping.full_name' => ['required', 'string', 'max:255'],
            'shipping.phone' => ['required', 'string', 'max:30'],
            'shipping.line1' => ['required', 'string', 'max:255'],
            'shipping.line2' => ['nullable', 'string', 'max:255'],
            'shipping.city' => ['required', 'string', 'max:120'],
            'shipping.governorate' => ['nullable', 'string', 'max:120'],
            'shipping.postal_code' => ['nullable', 'string', 'max:20'],
            'shipping.country' => ['nullable', 'string', 'size:2'],
            'billing.full_name' => ['nullable', 'string', 'max:255'],
            'billing.phone' => ['nullable', 'string', 'max:30'],
            'billing.line1' => ['nullable', 'string', 'max:255'],
            'billing.line2' => ['nullable', 'string', 'max:255'],
            'billing.city' => ['nullable', 'string', 'max:120'],
            'billing.governorate' => ['nullable', 'string', 'max:120'],
            'billing.postal_code' => ['nullable', 'string', 'max:20'],
            'billing.country' => ['nullable', 'string', 'size:2'],
        ];
    }

    public function messages(): array
    {
        return [
            'terms_agreed.accepted' => 'Please agree to the order terms before placing your order.',
        ];
    }
}
