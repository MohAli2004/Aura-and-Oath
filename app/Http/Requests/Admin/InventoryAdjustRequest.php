<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class InventoryAdjustRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'exists:products,id'],
            'product_variant_id' => ['nullable', 'exists:product_variants,id'],
            'quantity_change' => ['required', 'integer', 'not_in:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $raw = $this->input('quantity_change');

        if (! is_string($raw)) {
            return;
        }

        $trimmed = trim($raw);

        if ($trimmed === '' || $trimmed === '+' || $trimmed === '-') {
            return;
        }

        if (preg_match('/^([+-]?)(\d+)$/', $trimmed, $matches)) {
            $amount = (int) $matches[2];

            $this->merge([
                'quantity_change' => $matches[1] === '-' ? -$amount : $amount,
            ]);
        }
    }
}
