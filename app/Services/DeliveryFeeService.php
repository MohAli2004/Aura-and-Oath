<?php

namespace App\Services;

use App\Models\DeliveryRegion;

class DeliveryFeeService
{
    public function feeForRegion(?int $regionId): float
    {
        if (! $regionId) {
            return 0.0;
        }

        $region = DeliveryRegion::query()->active()->find($regionId);

        return $region ? (float) $region->fee : 0.0;
    }

    public function activeRegions()
    {
        return DeliveryRegion::query()->active()->orderBy('sort_order')->orderBy('name')->get();
    }
}
