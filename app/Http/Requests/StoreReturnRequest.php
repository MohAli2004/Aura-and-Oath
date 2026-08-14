<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'order_number' => ['required_without:order_id', 'string', 'max:50'],
            'email' => ['required_without:order_id', 'email', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:8', 'max:1000'],
            'photo' => ['required', 'image', 'max:4096'],
            'policy_accepted' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Select at least one item to return.',
            'items.min' => 'Select at least one item to return.',
            'items.*.id.required' => 'Select at least one item to return.',
            'items.*.quantity.required' => 'Enter how many of each selected item to return.',
            'items.*.quantity.min' => 'Amount to return must be at least 1.',
            'reason.min' => 'Please tell us a little more about why you are returning this.',
            'photo.required' => 'Upload a photo of the item, or take a picture.',
            'photo.image' => 'The photo must be an image file.',
            'policy_accepted.accepted' => 'Please confirm this return meets our return rules.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $items = $this->input('items', []);
        if (! is_array($items)) {
            return;
        }

        $normalized = [];
        foreach ($items as $key => $row) {
            if (is_numeric($row)) {
                $normalized[] = [
                    'id' => (int) $row,
                    'quantity' => 1,
                ];

                continue;
            }

            if (! is_array($row) || empty($row['id'])) {
                continue;
            }

            $normalized[] = [
                'id' => (int) $row['id'],
                'quantity' => (int) ($row['quantity'] ?? 0),
            ];
        }

        $this->merge(['items' => $normalized]);
    }
}
