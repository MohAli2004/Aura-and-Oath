<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportService
{
    public function dashboardStats(): array
    {
        $today = Carbon::today();

        return [
            'orders_today' => Order::query()->whereDate('created_at', $today)->count(),
            'revenue_today' => (float) Order::query()
                ->whereDate('created_at', $today)
                ->whereNotIn('status', [OrderStatus::Cancelled->value, OrderStatus::Rejected->value])
                ->sum('total'),
            'pending_approval' => Order::query()->pendingApproval()->count(),
            'low_stock' => Product::query()
                ->where('track_inventory', true)
                ->where('stock_status', 'low_stock')
                ->count(),
            'out_of_stock' => Product::query()
                ->where('track_inventory', true)
                ->where('stock_status', 'out_of_stock')
                ->count(),
            'customers' => User::query()->where('role', 'customer')->count(),
            'products_active' => Product::query()->where('status', 'active')->count(),
            'revenue_month' => (float) Order::query()
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->whereNotIn('status', [OrderStatus::Cancelled->value, OrderStatus::Rejected->value])
                ->sum('total'),
        ];
    }

    public function salesByDay(int $days = 30): Collection
    {
        return Order::query()
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('COUNT(*) as orders'), DB::raw('SUM(total) as revenue'))
            ->where('created_at', '>=', now()->subDays($days))
            ->whereNotIn('status', [OrderStatus::Cancelled->value, OrderStatus::Rejected->value])
            ->groupBy('day')
            ->orderBy('day')
            ->get();
    }

    public function topProducts(int $limit = 10): Collection
    {
        return OrderItem::query()
            ->select('product_id', 'product_name', DB::raw('SUM(quantity) as qty'), DB::raw('SUM(line_total) as revenue'))
            ->whereNotNull('product_id')
            ->groupBy('product_id', 'product_name')
            ->orderByDesc('qty')
            ->limit($limit)
            ->get();
    }

    public function exportOrdersCsv(?Carbon $from = null, ?Carbon $to = null): StreamedResponse
    {
        $filename = 'orders-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($from, $to) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Order Number', 'Date', 'Customer', 'Email', 'Status', 'Payment', 'Subtotal', 'Discount', 'Delivery', 'Total']);

            Order::query()
                ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
                ->orderByDesc('id')
                ->chunk(200, function ($orders) use ($out) {
                    foreach ($orders as $order) {
                        fputcsv($out, [
                            $order->order_number,
                            $order->created_at?->toDateTimeString(),
                            $order->customer_name,
                            $order->customer_email,
                            $order->status->value,
                            $order->payment_status->value,
                            $order->subtotal,
                            $order->discount_amount,
                            $order->delivery_fee,
                            $order->total,
                        ]);
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
