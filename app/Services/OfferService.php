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
     * @param  list<array{id:int,quantity?:int|string}>  $items
     */
    public function syncProducts(Offer $offer, array $items): void
    {
        $normalized = [];

        foreach (array_values($items) as $index => $item) {
            $productId = (int) ($item['id'] ?? 0);
            if ($productId < 1) {
                continue;
            }

            $normalized[] = [
                'id' => $productId,
                'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                'sort_order' => $index,
            ];
        }

        $lineTotals = $this->allocateLineTotals(
            $normalized,
            round((float) ($offer->total_price ?? 0), 2)
        );

        $payload = [];

        foreach ($normalized as $item) {
            $payload[$item['id']] = [
                'offer_price' => $lineTotals[$item['id']] ?? 0.0,
                'quantity' => $item['quantity'],
                'sort_order' => $item['sort_order'],
            ];
        }

        $offer->products()->sync($payload);
        $this->forgetCache();
    }

    /**
     * Split the offer total across lines by regular value so checkout can still store line amounts.
     * Shoppers and admins only see the total, not a per-item markdown.
     *
     * @param  list<array{id:int,quantity:int,sort_order:int}>  $items
     * @return array<int, float>
     */
    protected function allocateLineTotals(array $items, float $offerTotal): array
    {
        if ($items === []) {
            return [];
        }

        $products = Product::query()
            ->whereIn('id', array_column($items, 'id'))
            ->get(['id', 'price'])
            ->keyBy('id');

        $weights = [];
        $weightSum = 0;

        foreach ($items as $item) {
            $regular = (float) ($products->get($item['id'])?->price ?? 0) * $item['quantity'];
            $weight = (int) max(1, (int) round($regular * 100));
            $weights[$item['id']] = $weight;
            $weightSum += $weight;
        }

        $totalCents = (int) round($offerTotal * 100);
        $allocated = [];
        $used = 0;
        $lastId = (int) $items[array_key_last($items)]['id'];

        foreach ($items as $item) {
            $id = $item['id'];
            if ($id === $lastId) {
                continue;
            }

            $share = (int) floor($totalCents * $weights[$id] / $weightSum);
            $allocated[$id] = round($share / 100, 2);
            $used += $share;
        }

        $allocated[$lastId] = round(($totalCents - $used) / 100, 2);

        return $allocated;
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
            ->filter(fn (Offer $offer) => $offer->products->isNotEmpty())
            ->values();
    }

    public function hasLiveOffers(): bool
    {
        $resolve = function () {
            try {
                return Offer::query()
                    ->active()
                    ->whereHas('products', fn ($query) => $query->active()->published())
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
            ->filter(fn (Offer $offer) => $offer->isLive() && $offer->products->isNotEmpty());
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
            ->filter(fn (Offer $offer) => $offer->products->isNotEmpty())
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
