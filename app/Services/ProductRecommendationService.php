<?php

namespace App\Services;

use App\Enums\ProductGender;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RecentlyViewedProduct;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductRecommendationService
{
    private const WEIGHT_CATEGORY = 35;

    private const WEIGHT_BRAND = 18;

    private const WEIGHT_GENDER = 12;

    private const WEIGHT_PRICE = 10;

    private const WEIGHT_CO_PURCHASE = 22;

    private const WEIGHT_CO_VIEW = 12;

    private const WEIGHT_WISHLIST = 8;

    private const WEIGHT_PERSONAL = 15;

    private const WEIGHT_QUALITY = 8;

    private const WEIGHT_IN_STOCK = 5;

    public function forProduct(
        Product $product,
        ?User $user = null,
        ?string $sessionId = null,
        int $limit = 4,
    ): Collection {
        $product->loadMissing(['categories', 'brand']);

        $categoryIds = $product->categories->pluck('id')->map(fn ($id) => (int) $id)->all();
        $price = (float) $product->effectivePrice();
        $excludeIds = [(int) $product->id];

        $coPurchaseScores = $this->coPurchaseScores((int) $product->id);
        $coViewScores = $this->coViewScores((int) $product->id);
        $wishlistScores = $this->wishlistScores((int) $product->id);
        $personal = $this->personalAffinity($user, $sessionId, $excludeIds);

        $candidateIds = collect()
            ->merge($this->categoryCandidateIds($categoryIds, $excludeIds))
            ->merge($product->brand_id ? $this->brandCandidateIds((int) $product->brand_id, $excludeIds) : [])
            ->merge(array_keys($coPurchaseScores))
            ->merge(array_keys($coViewScores))
            ->merge(array_keys($wishlistScores))
            ->merge($personal['preferred_product_ids'] ?? [])
            ->unique()
            ->values()
            ->all();

        if ($candidateIds === []) {
            return $this->fallbackBestsellers($excludeIds, $limit);
        }

        $candidates = Product::query()
            ->with(['images', 'brand', 'categories', 'activeVariants'])
            ->active()
            ->published()
            ->whereIn('id', $candidateIds)
            ->whereNotIn('id', $excludeIds)
            ->get();

        if ($candidates->isEmpty()) {
            return $this->fallbackBestsellers($excludeIds, $limit);
        }

        $scored = $candidates->map(function (Product $candidate) use (
            $product,
            $categoryIds,
            $price,
            $coPurchaseScores,
            $coViewScores,
            $wishlistScores,
            $personal,
        ) {
            $breakdown = $this->scoreCandidate(
                $product,
                $candidate,
                $categoryIds,
                $price,
                $coPurchaseScores,
                $coViewScores,
                $wishlistScores,
                $personal,
            );

            $candidate->recommendation_score = $breakdown['score'];
            $candidate->match_percent = $breakdown['percent'];
            $candidate->match_reason = $breakdown['reason'];

            return $candidate;
        })
            ->sortByDesc(fn (Product $item) => [$item->recommendation_score, $item->is_bestseller ? 1 : 0, $item->id])
            ->values();

        $top = $scored->take($limit)->values();

        if ($top->count() < $limit) {
            $needed = $limit - $top->count();
            $filler = $this->fallbackBestsellers(
                array_merge($excludeIds, $top->pluck('id')->all()),
                $needed,
            );
            $top = $top->concat($filler)->values();
        }

        return $top;
    }

    /**
     * @param  list<int>  $categoryIds
     * @param  array<int, float>  $coPurchaseScores
     * @param  array<int, float>  $coViewScores
     * @param  array<int, float>  $wishlistScores
     * @param  array{preferred_brand_ids: list<int>, preferred_category_ids: list<int>, preferred_genders: list<string>, preferred_product_ids: list<int>}  $personal
     * @return array{score: float, percent: int, reason: string}
     */
    protected function scoreCandidate(
        Product $source,
        Product $candidate,
        array $categoryIds,
        float $sourcePrice,
        array $coPurchaseScores,
        array $coViewScores,
        array $wishlistScores,
        array $personal,
    ): array {
        $score = 0.0;
        $reasons = [];

        $candidateCategoryIds = $candidate->categories->pluck('id')->map(fn ($id) => (int) $id)->all();
        $sharedCategories = count(array_intersect($categoryIds, $candidateCategoryIds));
        if ($sharedCategories > 0 && $categoryIds !== []) {
            $overlap = $sharedCategories / max(count($categoryIds), count($candidateCategoryIds), 1);
            $categoryScore = self::WEIGHT_CATEGORY * min(1, 0.55 + ($overlap * 0.45) + (($sharedCategories - 1) * 0.1));
            $score += $categoryScore;
            $reasons[] = 'same category';
        }

        if ($source->brand_id && (int) $candidate->brand_id === (int) $source->brand_id) {
            $score += self::WEIGHT_BRAND;
            $reasons[] = 'same brand';
        }

        $genderScore = $this->genderScore($source->gender, $candidate->gender);
        if ($genderScore > 0) {
            $score += $genderScore;
            if ($genderScore >= self::WEIGHT_GENDER) {
                $reasons[] = 'same gender';
            }
        }

        $candidatePrice = (float) $candidate->effectivePrice();
        if ($sourcePrice > 0 && $candidatePrice > 0) {
            $delta = abs($candidatePrice - $sourcePrice) / $sourcePrice;
            if ($delta <= 0.35) {
                $priceScore = self::WEIGHT_PRICE * (1 - ($delta / 0.35));
                $score += $priceScore;
                if ($priceScore >= self::WEIGHT_PRICE * 0.6) {
                    $reasons[] = 'similar price';
                }
            }
        }

        $coBuy = $coPurchaseScores[(int) $candidate->id] ?? 0.0;
        if ($coBuy > 0) {
            $score += self::WEIGHT_CO_PURCHASE * $coBuy;
            $reasons[] = 'often bought together';
        }

        $coView = $coViewScores[(int) $candidate->id] ?? 0.0;
        if ($coView > 0) {
            $score += self::WEIGHT_CO_VIEW * $coView;
            $reasons[] = 'often viewed together';
        }

        $wish = $wishlistScores[(int) $candidate->id] ?? 0.0;
        if ($wish > 0) {
            $score += self::WEIGHT_WISHLIST * $wish;
            $reasons[] = 'saved together';
        }

        $personalScore = 0.0;
        if (in_array((int) $candidate->brand_id, $personal['preferred_brand_ids'], true)) {
            $personalScore += self::WEIGHT_PERSONAL * 0.45;
        }
        if (count(array_intersect($candidateCategoryIds, $personal['preferred_category_ids'])) > 0) {
            $personalScore += self::WEIGHT_PERSONAL * 0.4;
        }
        if ($candidate->gender && in_array($candidate->gender->value, $personal['preferred_genders'], true)) {
            $personalScore += self::WEIGHT_PERSONAL * 0.15;
        }
        if ($personalScore > 0) {
            $score += min(self::WEIGHT_PERSONAL, $personalScore);
            $reasons[] = 'matches your taste';
        }

        $quality = 0.0;
        if ($candidate->is_bestseller) {
            $quality += 4;
            $reasons[] = 'bestseller';
        }
        if ($candidate->is_featured) {
            $quality += 2.5;
        }
        if ($candidate->is_new) {
            $quality += 1.5;
        }
        $score += min(self::WEIGHT_QUALITY, $quality);

        if ($candidate->isPurchasable()) {
            $score += self::WEIGHT_IN_STOCK;
        } else {
            $score *= 0.35;
        }

        $maxPossible = self::WEIGHT_CATEGORY
            + self::WEIGHT_BRAND
            + self::WEIGHT_GENDER
            + self::WEIGHT_PRICE
            + self::WEIGHT_CO_PURCHASE
            + self::WEIGHT_CO_VIEW
            + self::WEIGHT_WISHLIST
            + self::WEIGHT_PERSONAL
            + self::WEIGHT_QUALITY
            + self::WEIGHT_IN_STOCK;

        $percent = (int) max(1, min(99, round(($score / $maxPossible) * 100)));

        $reason = $reasons[0] ?? 'recommended for you';

        return [
            'score' => round($score, 3),
            'percent' => $percent,
            'reason' => $reason,
        ];
    }

    protected function genderScore(?ProductGender $source, ?ProductGender $candidate): float
    {
        if (! $source || ! $candidate) {
            return self::WEIGHT_GENDER * 0.25;
        }

        if ($source === $candidate) {
            return self::WEIGHT_GENDER;
        }

        if ($source === ProductGender::Unisex || $candidate === ProductGender::Unisex) {
            return self::WEIGHT_GENDER * 0.65;
        }

        return 0.0;
    }

    /**
     * @return array<int, float> product_id => 0..1 strength
     */
    protected function coPurchaseScores(int $productId): array
    {
        $orderIds = OrderItem::query()
            ->accepted()
            ->where('product_id', $productId)
            ->distinct()
            ->limit(250)
            ->pluck('order_id');

        if ($orderIds->isEmpty()) {
            return [];
        }

        $rows = OrderItem::query()
            ->accepted()
            ->whereIn('order_id', $orderIds)
            ->where('product_id', '!=', $productId)
            ->select('product_id', DB::raw('COUNT(DISTINCT order_id) as together_count'))
            ->groupBy('product_id')
            ->orderByDesc('together_count')
            ->limit(40)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $max = max(1, (int) $rows->max('together_count'));

        return $rows
            ->mapWithKeys(fn ($row) => [(int) $row->product_id => ((int) $row->together_count) / $max])
            ->all();
    }

    /**
     * @return array<int, float>
     */
    protected function coViewScores(int $productId): array
    {
        $viewerKeys = RecentlyViewedProduct::query()
            ->where('product_id', $productId)
            ->where('viewed_at', '>=', now()->subDays(60))
            ->limit(400)
            ->get(['user_id', 'session_id']);

        if ($viewerKeys->isEmpty()) {
            return [];
        }

        $sessionIds = $viewerKeys->pluck('session_id')->filter()->unique()->values();
        $userIds = $viewerKeys->pluck('user_id')->filter()->unique()->values();

        $query = RecentlyViewedProduct::query()
            ->where('product_id', '!=', $productId)
            ->where('viewed_at', '>=', now()->subDays(60));

        $query->where(function ($q) use ($sessionIds, $userIds) {
            if ($sessionIds->isNotEmpty()) {
                $q->orWhereIn('session_id', $sessionIds);
            }
            if ($userIds->isNotEmpty()) {
                $q->orWhereIn('user_id', $userIds);
            }
        });

        $rows = $query
            ->select('product_id', DB::raw('COUNT(*) as view_count'))
            ->groupBy('product_id')
            ->orderByDesc('view_count')
            ->limit(40)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $max = max(1, (int) $rows->max('view_count'));

        return $rows
            ->mapWithKeys(fn ($row) => [(int) $row->product_id => min(1, ((int) $row->view_count) / $max)])
            ->all();
    }

    /**
     * @return array<int, float>
     */
    protected function wishlistScores(int $productId): array
    {
        $wishlistIds = WishlistItem::query()
            ->where('product_id', $productId)
            ->limit(300)
            ->pluck('wishlist_id');

        if ($wishlistIds->isEmpty()) {
            return [];
        }

        $rows = WishlistItem::query()
            ->whereIn('wishlist_id', $wishlistIds)
            ->where('product_id', '!=', $productId)
            ->select('product_id', DB::raw('COUNT(DISTINCT wishlist_id) as together_count'))
            ->groupBy('product_id')
            ->orderByDesc('together_count')
            ->limit(30)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $max = max(1, (int) $rows->max('together_count'));

        return $rows
            ->mapWithKeys(fn ($row) => [(int) $row->product_id => ((int) $row->together_count) / $max])
            ->all();
    }

    /**
     * @param  list<int>  $excludeIds
     * @return array{preferred_brand_ids: list<int>, preferred_category_ids: list<int>, preferred_genders: list<string>, preferred_product_ids: list<int>}
     */
    protected function personalAffinity(?User $user, ?string $sessionId, array $excludeIds): array
    {
        $empty = [
            'preferred_brand_ids' => [],
            'preferred_category_ids' => [],
            'preferred_genders' => [],
            'preferred_product_ids' => [],
        ];

        $recentQuery = RecentlyViewedProduct::query()
            ->where('viewed_at', '>=', now()->subDays(30))
            ->whereNotIn('product_id', $excludeIds)
            ->latest('viewed_at')
            ->limit(25);

        if ($user) {
            $recentQuery->where(function ($q) use ($user, $sessionId) {
                $q->where('user_id', $user->id);
                if ($sessionId) {
                    $q->orWhere('session_id', $sessionId);
                }
            });
        } elseif ($sessionId) {
            $recentQuery->where('session_id', $sessionId);
        } else {
            return $empty;
        }

        $recentProductIds = $recentQuery->pluck('product_id')->map(fn ($id) => (int) $id)->unique()->values();

        if ($user) {
            $wishlistProductIds = WishlistItem::query()
                ->whereHas('wishlist', fn ($q) => $q->where('user_id', $user->id))
                ->whereNotIn('product_id', $excludeIds)
                ->limit(40)
                ->pluck('product_id')
                ->map(fn ($id) => (int) $id);

            $purchasedIds = OrderItem::query()
                ->accepted()
                ->whereHas('order', fn ($q) => $q->where('user_id', $user->id))
                ->whereNotIn('product_id', $excludeIds)
                ->latest('id')
                ->limit(40)
                ->pluck('product_id')
                ->map(fn ($id) => (int) $id);

            $recentProductIds = $recentProductIds
                ->merge($wishlistProductIds)
                ->merge($purchasedIds)
                ->unique()
                ->values();
        }

        if ($recentProductIds->isEmpty()) {
            return $empty;
        }

        $products = Product::query()
            ->with('categories')
            ->whereIn('id', $recentProductIds->all())
            ->get(['id', 'brand_id', 'gender']);

        return [
            'preferred_brand_ids' => $products->pluck('brand_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all(),
            'preferred_category_ids' => $products->flatMap(fn (Product $p) => $p->categories->pluck('id'))->map(fn ($id) => (int) $id)->unique()->values()->all(),
            'preferred_genders' => $products->pluck('gender')->filter()->map(fn ($g) => $g instanceof ProductGender ? $g->value : (string) $g)->unique()->values()->all(),
            'preferred_product_ids' => $recentProductIds->take(20)->all(),
        ];
    }

    /**
     * @param  list<int>  $categoryIds
     * @param  list<int>  $excludeIds
     * @return list<int>
     */
    protected function categoryCandidateIds(array $categoryIds, array $excludeIds): array
    {
        if ($categoryIds === []) {
            return [];
        }

        return Product::query()
            ->active()
            ->published()
            ->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $categoryIds))
            ->whereNotIn('id', $excludeIds)
            ->orderByDesc('is_bestseller')
            ->orderByDesc('is_featured')
            ->limit(60)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  list<int>  $excludeIds
     * @return list<int>
     */
    protected function brandCandidateIds(int $brandId, array $excludeIds): array
    {
        return Product::query()
            ->active()
            ->published()
            ->where('brand_id', $brandId)
            ->whereNotIn('id', $excludeIds)
            ->orderByDesc('is_bestseller')
            ->limit(30)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  list<int>  $excludeIds
     */
    protected function fallbackBestsellers(array $excludeIds, int $limit): Collection
    {
        if ($limit < 1) {
            return collect();
        }

        return Product::query()
            ->with(['images', 'brand', 'activeVariants'])
            ->active()
            ->published()
            ->whereNotIn('id', $excludeIds)
            ->orderByDesc('is_bestseller')
            ->orderByDesc('is_featured')
            ->orderByDesc('created_at')
            ->take($limit)
            ->get()
            ->each(function (Product $product) {
                $product->recommendation_score = 1;
                $product->match_percent = 35;
                $product->match_reason = 'popular pick';
            });
    }
}
