<?php

namespace App\Services;

use App\Enums\ProductStatus;
use App\Enums\ProductVisibility;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ProductSearchService
{
    public function search(array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        $query = Product::query()
            ->with(['brand', 'images', 'activeVariants'])
            ->active()
            ->published();

        if (! empty($filters['q'])) {
            $q = trim((string) $filters['q']);
            $like = '%'.$q.'%';
            $query->where(function (Builder $builder) use ($q, $like) {
                $builder->where('name', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    ->orWhere('barcode', 'like', $like)
                    ->orWhere('short_description', 'like', $like)
                    ->orWhereHas('variants', function (Builder $variants) use ($like) {
                        $variants->where('barcode', 'like', $like)
                            ->orWhere('sku', 'like', $like);
                    });
            });
        }

        if (! empty($filters['category'])) {
            $query->whereHas('categories', fn (Builder $b) => $b->where('slug', $filters['category']));
        }

        if (! empty($filters['category_id'])) {
            $query->whereHas('categories', fn (Builder $b) => $b->where('categories.id', $filters['category_id']));
        }

        if (! empty($filters['brand'])) {
            $query->whereHas('brand', fn (Builder $b) => $b->where('slug', $filters['brand']));
        }

        if (! empty($filters['brand_id'])) {
            $query->where('brand_id', $filters['brand_id']);
        }

        if (isset($filters['min_price'])) {
            $query->where('price', '>=', $filters['min_price']);
        }

        if (isset($filters['max_price'])) {
            $query->where('price', '<=', $filters['max_price']);
        }

        if (! empty($filters['featured'])) {
            $query->featured();
        }

        if (! empty($filters['gender'])) {
            $query->gender($filters['gender']);
        }

        if (! empty($filters['in_stock'])) {
            $query->where('stock_status', '!=', 'out_of_stock');
        }

        $sort = $filters['sort'] ?? 'newest';
        match ($sort) {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'name' => $query->orderBy('name'),
            'featured' => $query->orderByDesc('is_featured')->orderByDesc('created_at'),
            default => $query->orderByDesc('created_at'),
        };

        return $query->paginate($perPage)->withQueryString();
    }

    public function adminSearch(array $filters = [], int $perPage = 20, bool $onlyTrashed = false, ?string $list = null): LengthAwarePaginator
    {
        $query = Product::query()->with(['categories', 'brand', 'images', 'activeVariants']);

        if ($onlyTrashed) {
            $query->onlyTrashed();
        } elseif ($list === 'active') {
            $query->where('status', ProductStatus::Active);
        } elseif ($list === 'inactive') {
            $inactive = [ProductStatus::Draft->value, ProductStatus::Archived->value];
            if (! empty($filters['status']) && in_array($filters['status'], $inactive, true)) {
                $query->where('status', $filters['status']);
            } else {
                $query->whereIn('status', $inactive);
            }
        }

        if (! empty($filters['q'])) {
            $q = trim((string) $filters['q']);
            $like = '%'.$q.'%';
            $query->where(function (Builder $builder) use ($like) {
                $builder->where('name', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    ->orWhere('barcode', 'like', $like)
                    ->orWhereHas('variants', function (Builder $variants) use ($like) {
                        $variants->where('barcode', 'like', $like)
                            ->orWhere('sku', 'like', $like);
                    });
            });
        }

        if (! empty($filters['status']) && $list !== 'active' && $list !== 'inactive' && ! $onlyTrashed) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['category_id'])) {
            $query->whereHas('categories', fn (Builder $b) => $b->where('categories.id', $filters['category_id']));
        }

        if (! empty($filters['gender'])) {
            $query->gender($filters['gender']);
        }

        return ($onlyTrashed ? $query->orderByDesc('deleted_at') : $query->latest())
            ->paginate($perPage)
            ->withQueryString();
    }
}
