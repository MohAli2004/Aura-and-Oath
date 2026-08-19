<?php

namespace App\Http\Requests\Admin;

use App\Models\Offer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        /** @var Offer|null $offer */
        $offer = $this->route('offer');

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('offers', 'slug')->ignore($offer?->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'image' => ['nullable', 'image', 'max:4096'],
            'products' => ['required', 'array', 'min:2'],
            'products.*.id' => ['required', 'integer', 'exists:products,id', 'distinct'],
            'products.*.offer_price' => ['required', 'numeric', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'starts_at' => $this->filled('starts_at') ? $this->input('starts_at') : null,
            'ends_at' => $this->filled('ends_at') ? $this->input('ends_at') : null,
        ]);
    }

    public function messages(): array
    {
        return [
            'products.min' => 'Add at least two products. Customers must buy the full set to get the offer price.',
        ];
    }
}
