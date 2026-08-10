<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeliveryRegionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        $regionId = $this->route('delivery_region')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('delivery_regions', 'code')->ignore($regionId)],
            'fee' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'estimated_days_min' => ['required', 'integer', 'min:0'],
            'estimated_days_max' => ['required', 'integer', 'min:0', 'gte:estimated_days_min'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $code = trim((string) $this->input('code'));
            $this->merge(['code' => $code !== '' ? strtoupper($code) : null]);
        }

        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'sort_order' => $this->filled('sort_order') ? $this->input('sort_order') : 0,
        ]);
    }
}
