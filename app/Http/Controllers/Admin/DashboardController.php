<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Services\ReportService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(ReportService $reports): View
    {
        return view('admin.dashboard', [
            'stats' => $reports->dashboardStats(),
            'sales' => $reports->salesByDay(14),
            'topProducts' => $reports->topProducts(5),
            'pendingOrders' => Order::query()->pendingApproval()->latest()->take(8)->get(),
            'lowStockProducts' => Product::query()->whereIn('stock_status', ['low_stock', 'out_of_stock'])->latest()->take(8)->get(),
        ]);
    }
}
