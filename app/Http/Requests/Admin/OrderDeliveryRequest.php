<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class OrderDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'delivery_region_id' => ['required', 'integer', 'exists:delivery_regions,id'],
            'delivery_fee' => ['required', 'numeric', 'min:0', 'max:99999'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
