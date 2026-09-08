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
            'items.variant.images',
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
        $this->authorize('view', $order);

        if ($order->status === \App\Enums\OrderStatus::Cancelled) {
            return redirect()
                ->route('account.orders.index')
                ->with('error', __('storefront.flash_order_already_cancelled'));
        }

        $this->authorize('cancel', $order);
        $orderService->cancel($order, Auth::user(), 'Cancelled by customer');

        return redirect()
            ->route('account.orders.index')
            ->with('success', __('storefront.flash_order_cancelled'));
    }

    public function cancelLookup(Request $request, OrderService $orderService): RedirectResponse
    {
        $data = $request->validate([
            'order_number' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $order = Order::query()
            ->where('order_number', $data['order_number'])
            ->where('customer_email', $data['email'])
            ->first();

        if (! $order) {
            return back()
                ->withInput()
                ->with('error', __('storefront.flash_order_not_found'));
        }

        if ($order->status === \App\Enums\OrderStatus::Cancelled) {
            return redirect()
                ->route('account.orders.index')
                ->with('error', __('storefront.flash_order_already_cancelled'));
        }

        if (! $order->canCustomerCancel()) {
            return back()->with(
                'error',
                __('storefront.flash_cannot_cancel_order')
            );
        }

        $actor = ($request->user() && $order->isOwnedBy($request->user()))
            ? $request->user()
            : $order->user;

        if (! $actor) {
            return back()->with('error', __('storefront.flash_order_account_mismatch'));
        }

        $orderService->cancel($order, $actor, 'Cancelled by customer');

        return redirect()
            ->route('account.orders.index')
            ->with('success', __('storefront.flash_order_cancelled'));
    }
}
