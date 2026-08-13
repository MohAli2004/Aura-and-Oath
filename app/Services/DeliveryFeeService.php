<?php

namespace App\Services;

use App\Models\DeliveryRegion;
use Illuminate\Validation\ValidationException;

class DeliveryFeeService
{
    public function feeForRegion(?int $regionId): float
    {
        if (! $regionId) {
            return 0.0;
        }

        $region = DeliveryRegion::query()->active()->find($regionId);

        if (! $region) {
            throw ValidationException::withMessages([
                'delivery_region_id' => 'Please select a valid delivery region.',
            ]);
        }

        return (float) $region->fee;
    }

    public function activeRegions()
    {
        return DeliveryRegion::query()->active()->orderBy('sort_order')->orderBy('name')->get();
    }
}
