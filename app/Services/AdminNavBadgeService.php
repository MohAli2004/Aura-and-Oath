<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\StockStatus;
use App\Enums\UserRole;
use App\Models\AdminNavSeen;
use App\Models\Attribute;
use App\Models\AuditLog;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\DeliveryRegion;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Schema;

class AdminNavBadgeService
{
    /** Fallback window when a section has never been opened. */
    private const INITIAL_LOOKBACK_DAYS = 14;

    /**
     * Map admin route names to badge section keys.
     *
     * @var array<string, string>
     */
    public const ROUTE_SECTIONS = [
        'admin.products.index' => 'products',
        'admin.categories.index' => 'categories',
        'admin.brands.index' => 'brands',
        'admin.attributes.index' => 'attributes',
        'admin.inventory.index' => 'inventory',
        'admin.orders.index' => 'orders',
        'admin.customers.index' => 'customers',
        'admin.coupons.index' => 'coupons',
        'admin.delivery-regions.index' => 'delivery',
        'admin.banners.index' => 'banners',
        'admin.notifications.index' => 'notifications',
        'admin.audit-logs.index' => 'audit',
    ];

    /**
     * @return array<string, int>
     */
    public function countsFor(User $user): array
    {
        if (! $this->tableReady()) {
            return [];
        }

        $seenAt = $this->seenMap($user);

        return [
            'products' => $this->countCreatedSince(Product::query(), $seenAt['products'] ?? null),
            'categories' => $this->countCreatedSince(Category::query(), $seenAt['categories'] ?? null),
            'brands' => $this->countCreatedSince(Brand::query(), $seenAt['brands'] ?? null),
            'attributes' => $this->countCreatedSince(Attribute::query(), $seenAt['attributes'] ?? null),
            'inventory' => $this->inventoryAttentionCount(),
            'orders' => $this->ordersCount($seenAt['orders'] ?? null),
            'customers' => $this->customersCount($seenAt['customers'] ?? null),
            'coupons' => $this->countCreatedSince(Coupon::query(), $seenAt['coupons'] ?? null),
            'delivery' => $this->countCreatedSince(DeliveryRegion::query(), $seenAt['delivery'] ?? null),
            'banners' => $this->countCreatedSince(Banner::query(), $seenAt['banners'] ?? null),
            'notifications' => $user->unreadNotifications()->count(),
            'audit' => $this->countCreatedSince(AuditLog::query(), $seenAt['audit'] ?? null),
        ];
    }

    public function markSeen(User $user, string $section): void
    {
        if (! $this->tableReady() || $section === '') {
            return;
        }

        AdminNavSeen::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'section' => $section,
            ],
            [
                'seen_at' => now(),
            ]
        );
    }

    public function sectionForRoute(?string $routeName): ?string
    {
        if (! $routeName) {
            return null;
        }

        return self::ROUTE_SECTIONS[$routeName] ?? null;
    }

    /**
     * @return array<string, CarbonInterface>
     */
    protected function seenMap(User $user): array
    {
        return AdminNavSeen::query()
            ->where('user_id', $user->id)
            ->get(['section', 'seen_at'])
            ->mapWithKeys(fn (AdminNavSeen $row) => [$row->section => $row->seen_at])
            ->all();
    }

    protected function baseline(?CarbonInterface $seenAt): CarbonInterface
    {
        return $seenAt ?? now()->subDays(self::INITIAL_LOOKBACK_DAYS);
    }

    protected function countCreatedSince($query, ?CarbonInterface $seenAt): int
    {
        return (int) $query
            ->where('created_at', '>', $this->baseline($seenAt))
            ->count();
    }

    protected function customersCount(?CarbonInterface $seenAt): int
    {
        return (int) User::query()
            ->where('role', UserRole::Customer)
            ->where('created_at', '>', $this->baseline($seenAt))
            ->count();
    }

    protected function ordersCount(?CarbonInterface $seenAt): int
    {
        $newOrders = (int) Order::query()
            ->where('created_at', '>', $this->baseline($seenAt))
            ->count();

        $pending = (int) Order::query()
            ->whereIn('status', [
                OrderStatus::PendingApproval,
                OrderStatus::ReturnRequested,
            ])
            ->count();

        // Prefer actionable pending count when it is higher than unseen news.
        return max($newOrders, $pending);
    }

    protected function inventoryAttentionCount(): int
    {
        return (int) Product::query()
            ->whereIn('stock_status', [
                StockStatus::LowStock->value,
                StockStatus::OutOfStock->value,
            ])
            ->count();
    }

    protected function tableReady(): bool
    {
        try {
            return Schema::hasTable('admin_nav_seens');
        } catch (\Throwable) {
            return false;
        }
    }
}
