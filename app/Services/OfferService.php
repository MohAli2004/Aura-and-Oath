<?php

namespace App\Services;

use App\Models\Offer;
use App\Models\OfferProduct;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class OfferService
{
    public const PRICE_CACHE_KEY = 'storefront.active_offer_prices';

    public const LIVE_CACHE_KEY = 'storefront.has_live_offers';

    /**
     * @return array<int, float>
     */
    public function activePriceMap(): array
    {
        return Cache::remember(self::PRICE_CACHE_KEY, 300, function () {
            try {
                return OfferProduct::query()
                    ->whereHas('offer', fn ($query) => $query->active())
                    ->select('product_id', DB::raw('MIN(offer_price) as offer_price'))
                    ->groupBy('product_id')
                    ->pluck('offer_price', 'product_id')
                    ->map(fn ($price) => (float) $price)
                    ->all();
            } catch (\Throwable) {
                return [];
            }
        });
    }

    public function priceFor(int $productId): ?float
    {
        $map = $this->activePriceMap();

        return array_key_exists($productId, $map) ? $map[$productId] : null;
    }

    /**
     * @param  list<array{id:int,offer_price:float|int|string}>  $items
     */
    public function syncProducts(Offer $offer, array $items): void
    {
        $payload = [];

        foreach (array_values($items) as $index => $item) {
            $productId = (int) ($item['id'] ?? 0);
            if ($productId < 1) {
                continue;
            }

            $payload[$productId] = [
                'offer_price' => round((float) ($item['offer_price'] ?? 0), 2),
                'sort_order' => $index,
            ];
        }

        $offer->products()->sync($payload);
        $this->forgetCache();
    }

    public function liveOffers(int $limit = 12): Collection
    {
        return Offer::query()
            ->active()
            ->with(['products' => function ($query) {
                $query->with(['images', 'brand', 'activeVariants'])
                    ->active()
                    ->published();
            }])
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->filter(fn (Offer $offer) => $offer->products->count() >= 2)
            ->values();
    }

    public function hasLiveOffers(): bool
    {
        $resolve = function () {
            try {
                return Offer::query()
                    ->active()
                    ->whereHas('products', fn ($query) => $query->active()->published(), '>=', 2)
                    ->exists();
            } catch (\Throwable) {
                return false;
            }
        };

        if (app()->runningUnitTests()) {
            return $resolve();
        }

        return Cache::remember(self::LIVE_CACHE_KEY, 300, $resolve);
    }

    public function liveOffersForProduct(Product $product): Collection
    {
        return $product->offers()
            ->active()
            ->with(['products' => function ($query) {
                $query->with(['images', 'brand', 'activeVariants'])
                    ->active()
                    ->published();
            }])
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (Offer $offer) => $offer->isLive() && $offer->products->count() >= 2);
    }

    public function searchLive(string $query, int $limit = 8): Collection
    {
        $term = trim($query);
        if ($term === '') {
            return collect();
        }

        $like = '%'.$term.'%';

        return Offer::query()
            ->active()
            ->where(function ($builder) use ($like) {
                $builder->where('title', 'like', $like)
                    ->orWhere('description', 'like', $like);
            })
            ->with(['products' => function ($products) {
                $products->with(['images', 'brand', 'activeVariants'])
                    ->active()
                    ->published();
            }])
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->filter(fn (Offer $offer) => $offer->products->count() >= 2)
            ->values();
    }

    public function catalogProducts(): Collection
    {
        return Product::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'price']);
    }

    public function forgetCache(): void
    {
        Cache::forget(self::PRICE_CACHE_KEY);
        Cache::forget(self::LIVE_CACHE_KEY);
        Cache::forget('storefront.home');
    }
}
