<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(): View
    {
        $orders = Order::query()
            ->where('user_id', Auth::id())
            ->latest()
            ->paginate(10);

        return view('storefront.account.orders', compact('orders'));
    }

    public function show(Order $order): View
    {
        $this->authorize('view', $order);
        $order->load([
            'items.product.images',
            'items.product.activeVariants',
            'items.variant',
            'addresses',
            'statusHistories' => fn ($q) => $q->where('is_customer_visible', true),
            'notes' => fn ($q) => $q->where('is_customer_visible', true),
        ]);

        return view('storefront.account.order-show', compact('order'));
    }

    public function track(Request $request): View
    {
        $order = null;
        if ($request->filled('order_number') && $request->filled('email')) {
            $order = Order::query()
                ->where('order_number', $request->string('order_number'))
                ->where('customer_email', $request->string('email'))
                ->with([
                    'items',
                    'statusHistories' => fn ($q) => $q->where('is_customer_visible', true),
                ])
                ->first();
        }

        return view('storefront.track', [
            'order' => $order,
            'searched' => $request->filled('order_number'),
        ]);
    }

    public function cancel(Order $order, OrderService $orderService): RedirectResponse
    {
        $this->authorize('cancel', $order);
        $orderService->cancel($order, Auth::user(), 'Cancelled by customer');

        return back()->with('success', 'Order cancelled.');
    }
}
