<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Http\Requests\StoreReturnRequest;
use App\Models\Order;
use App\Models\Page;
use App\Services\ImageService;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReturnController extends Controller
{
    public function __construct(
        protected OrderService $orders,
        protected ImageService $images
    ) {}

    public function index(Request $request): View
    {
        $order = $this->findOrderFromLookup($request);
        $searched = $request->filled('order')
            || ($request->filled('order_number') && $request->filled('email'));

        if ($order) {
            $order->load([
                'items.product.images',
                'items.variant',
                'pendingReturnRequest.items.orderItem',
            ]);
        }

        $windowHours = max(1, (int) config('aura.orders.return_window_hours', 24));
        $eligibleOrders = collect();
        if ($request->user()) {
            $eligibleOrders = $request->user()
                ->orders()
                ->with(['items.product.images', 'items.variant', 'pendingReturnRequest'])
                ->where('status', OrderStatus::Delivered)
                ->where('delivered_at', '>=', now()->subHours($windowHours))
                ->latest()
                ->get()
                ->filter(fn (Order $row) => $row->canRequestReturn())
                ->values();
        }

        $policy = Page::query()->published()->where('slug', 'returns-policy')->first();

        return view('storefront.returns', [
            'order' => $order,
            'searched' => $searched,
            'eligibleOrders' => $eligibleOrders,
            'policy' => $policy,
            'windowHours' => $windowHours,
        ]);
    }

    public function store(StoreReturnRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $order = $this->resolveOrder($request, $data);

        if (! $order) {
            return back()
                ->withInput()
                ->with('error', 'Order not found. Check the number and email.');
        }

        $actor = $request->user() ?: $order->user;
        if (! $actor) {
            return back()
                ->withInput()
                ->with('error', 'We could not match this order to a customer account.');
        }

        $owned = $request->user() && $order->isOwnedBy($request->user());
        $verifiedLookup = ! empty($data['order_number'])
            && ! empty($data['email'])
            && strcasecmp((string) $order->order_number, (string) $data['order_number']) === 0
            && strcasecmp((string) $order->customer_email, (string) $data['email']) === 0;

        if (! $owned && ! $verifiedLookup) {
            return back()
                ->withInput()
                ->with('error', 'Order not found. Check the number and email.');
        }

        if (! $order->canRequestReturn()) {
            return back()
                ->withInput()
                ->with('error', $order->returnIneligibilityReason() ?: 'This order cannot be returned.');
        }

        $photoPath = $this->images->store($request->file('photo'), 'returns');

        $this->orders->requestReturn(
            $order,
            $actor,
            array_values($data['items']),
            $data['reason'],
            $photoPath
        );

        $redirect = $request->user() && $order->isOwnedBy($request->user())
            ? redirect()->route('account.orders.show', $order)
            : redirect()->route('returns.index', [
                'order_number' => $order->order_number,
                'email' => $order->customer_email,
            ]);

        return $redirect->with('success', 'Return requested. We will review it and get back to you.');
    }

    protected function findOrderFromLookup(Request $request): ?Order
    {
        if ($request->filled('order') && $request->user()) {
            $order = Order::query()->find($request->integer('order'));

            return $order && $order->isOwnedBy($request->user()) ? $order : null;
        }

        if ($request->filled('order_number') && $request->filled('email')) {
            return Order::query()
                ->where('order_number', $request->string('order_number'))
                ->where('customer_email', $request->string('email'))
                ->first();
        }

        return null;
    }

    /**
     * @param  array{order_id?:int|string, order_number?:string, email?:string}  $data
     */
    protected function resolveOrder(Request $request, array $data): ?Order
    {
        if (! empty($data['order_id'])) {
            $order = Order::query()->find($data['order_id']);
            if (! $order) {
                return null;
            }

            if ($request->user()) {
                return $order->isOwnedBy($request->user()) ? $order : null;
            }

            $email = strtolower((string) ($data['email'] ?? ''));
            if ($email !== '' && strtolower($order->customer_email) === $email) {
                return $order;
            }

            return null;
        }

        if (! empty($data['order_number']) && ! empty($data['email'])) {
            return Order::query()
                ->where('order_number', $data['order_number'])
                ->where('customer_email', $data['email'])
                ->first();
        }

        return null;
    }
}
