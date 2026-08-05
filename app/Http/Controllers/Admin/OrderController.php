<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OrderRejectRequest;
use App\Http\Requests\Admin\OrderStatusRequest;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(protected OrderService $orders) {}

    public function index(Request $request): View
    {
        $orders = Order::query()
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->q, function ($q) use ($request) {
                $term = $request->q;
                $q->where(function ($inner) use ($term) {
                    $inner->where('order_number', 'like', "%{$term}%")
                        ->orWhere('customer_email', 'like', "%{$term}%")
                        ->orWhere('customer_name', 'like', "%{$term}%")
                        ->orWhere('customer_phone', 'like', "%{$term}%");
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.orders.index', [
            'orders' => $orders,
            'statuses' => OrderStatus::cases(),
        ]);
    }

    public function show(Order $order): View
    {
        $order->load(['items', 'addresses', 'statusHistories.changer', 'notes.user', 'user']);

        return view('admin.orders.show', compact('order'));
    }

    public function approve(Order $order): RedirectResponse
    {
        $this->orders->approve($order, Auth::user());

        return back()->with('success', 'Order approved. Stock converted to sale.');
    }

    public function reject(OrderRejectRequest $request, Order $order): RedirectResponse
    {
        $this->orders->reject($order, Auth::user(), $request->validated('reason'));

        return back()->with('success', 'Order rejected. Reserved stock released.');
    }

    public function updateStatus(OrderStatusRequest $request, Order $order): RedirectResponse
    {
        $data = $request->validated();

        if (! empty($data['tracking_number'])) {
            $order->update(['tracking_number' => $data['tracking_number']]);
        }

        $this->orders->updateStatus($order, OrderStatus::from($data['status']), Auth::user(), $data['note'] ?? null);

        return back()->with('success', 'Status updated.');
    }

    public function addNote(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'is_customer_visible' => ['nullable', 'boolean'],
        ]);

        $this->orders->addNote($order, Auth::user(), $data['body'], $request->boolean('is_customer_visible'));

        return back()->with('success', 'Note added.');
    }

    public function confirmReturn(Request $request, Order $order): RedirectResponse
    {
        $this->orders->confirmReturnResellable($order, Auth::user(), $request->boolean('resellable', true), $request->input('note'));

        return back()->with('success', 'Return processed.');
    }

    public function invoice(Order $order): View
    {
        $order->load(['items', 'addresses']);

        return view('admin.orders.print.invoice', compact('order'));
    }

    public function packingSlip(Order $order): View
    {
        $order->load(['items', 'addresses']);

        return view('admin.orders.print.packing-slip', compact('order'));
    }

    public function markPaid(Request $request, Order $order): RedirectResponse
    {
        $this->orders->markPaid($order, Auth::user(), $request->input('note'));

        return back()->with('success', 'Payment marked as paid.');
    }
}
