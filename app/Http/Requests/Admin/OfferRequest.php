<?php

namespace App\Http\Requests\Admin;

use App\Models\Offer;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'total_price' => ['required', 'numeric', 'min:0'],
            'products' => ['required', 'array', 'min:1'],
            'products.*.id' => ['required', 'integer', 'exists:products,id', 'distinct'],
            'products.*.quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $cap = $this->regularItemsTotal();
            $offerTotal = round((float) $this->input('total_price'), 2);

            if ($offerTotal > $cap) {
                $validator->errors()->add(
                    'total_price',
                    'The offer total cannot be more than '.money($cap).' (the regular total of the items).'
                );
            }
        });
    }

    protected function regularItemsTotal(): float
    {
        $rows = collect($this->input('products', []));
        $ids = $rows->pluck('id')->map(fn ($id) => (int) $id)->filter()->unique()->all();
        $prices = Product::query()->whereIn('id', $ids)->pluck('price', 'id');

        return round((float) $rows->sum(function ($row) use ($prices) {
            $id = (int) ($row['id'] ?? 0);
            $qty = max(1, (int) ($row['quantity'] ?? 1));

            return (float) ($prices[$id] ?? 0) * $qty;
        }), 2);
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
            'products.min' => 'Add at least one product, and set how many of each are included.',
            'total_price.required' => 'Enter the offer total. Customers see this price, not a per-item price.',
        ];
    }
}
