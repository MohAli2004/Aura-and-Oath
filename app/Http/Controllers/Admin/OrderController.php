<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OrderItemQuantityRequest;
use App\Http\Requests\Admin\OrderRejectItemsRequest;
use App\Http\Requests\Admin\OrderRejectRequest;
use App\Http\Requests\Admin\OrderStatusRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(protected OrderService $orders) {}

    public function index(Request $request): View|Response
    {
        $list = $this->ordersList($request);
        $closedStatuses = $this->closedOrderStatuses();

        $activeCount = Order::query()
            ->whereNotIn('status', $closedStatuses)
            ->where(function ($q) {
                $q->where('payment_status', '!=', PaymentStatus::Paid)
                    ->orWhere('status', '!=', OrderStatus::Delivered);
            })
            ->count();
        $finishedCount = Order::query()
            ->where('status', OrderStatus::Delivered)
            ->where('payment_status', PaymentStatus::Paid)
            ->count();
        $closedCount = Order::query()
            ->whereIn('status', $closedStatuses)
            ->count();

        $orders = $this->filteredOrdersQuery($request)
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return response()
            ->view('admin.orders.index', [
                'orders' => $orders,
                'statuses' => $this->statusesForList($list),
                'list' => $list,
                'activeCount' => $activeCount,
                'finishedCount' => $finishedCount,
                'closedCount' => $closedCount,
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    public function printList(Request $request): View
    {
        $status = $request->filled('status')
            ? OrderStatus::tryFrom((string) $request->string('status'))
            : null;

        $orders = $this->filteredOrdersQuery($request)
            ->latest()
            ->limit(500)
            ->get();

        return view('admin.orders.print.list', [
            'orders' => $orders,
            'statusFilter' => $status,
            'search' => $request->string('q')->toString() ?: null,
            'list' => $this->ordersList($request),
            'printedAt' => now(),
        ]);
    }

    public function show(Order $order): View
    {
        $order->load([
            'items.product.images',
            'items.product.activeVariants',
            'items.variant',
            'addresses',
            'statusHistories.changer',
            'notes.user',
            'user',
        ]);

        return view('admin.orders.show', compact('order'));
    }

    public function approve(Order $order): RedirectResponse
    {
        $this->orders->approve($order, Auth::user());

        return $this->backAfterOrderChange($order, 'Order approved. Stock converted to sale.');
    }

    public function reject(OrderRejectRequest $request, Order $order): RedirectResponse
    {
        $this->orders->reject($order, Auth::user(), $request->validated('reason'));

        return $this->backAfterOrderChange($order->fresh(), 'Order rejected. Reserved stock released.');
    }

    public function rejectItems(OrderRejectItemsRequest $request, Order $order): RedirectResponse
    {
        $order = $this->orders->rejectItems(
            $order,
            Auth::user(),
            array_values($request->validated('items'))
        );

        if ($order->status === OrderStatus::Rejected) {
            return $this->backAfterOrderChange($order, 'All items rejected — order rejected.');
        }

        return $this->backAfterOrderChange($order, 'Selected items rejected. Totals updated. Approve when ready.');
    }

    public function restoreItem(Order $order, OrderItem $item): RedirectResponse
    {
        $this->orders->restoreItem($order, $item, Auth::user());

        return $this->backAfterOrderChange($order, 'Item restored and stock re-reserved.');
    }

    public function updateItemQuantity(OrderItemQuantityRequest $request, Order $order, OrderItem $item): RedirectResponse
    {
        $data = $request->validated();

        $this->orders->updateItemQuantity(
            $order,
            $item,
            (int) $data['quantity'],
            Auth::user(),
            $data['note'] ?? null
        );

        return $this->backAfterOrderChange($order, 'Item quantity updated. Customer has been notified.');
    }

    public function updateStatus(OrderStatusRequest $request, Order $order): RedirectResponse
    {
        $data = $request->validated();

        if (! empty($data['tracking_number'])) {
            $order->update(['tracking_number' => $data['tracking_number']]);
        }

        $this->orders->updateStatus($order, OrderStatus::from($data['status']), Auth::user(), $data['note'] ?? null);

        return $this->backAfterOrderChange($order->fresh(), 'Status updated.');
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

        return $this->backAfterOrderChange($order->fresh(), 'Return processed.');
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

        return $this->backAfterOrderChange($order->fresh(), 'Payment marked as paid.');
    }

    public function undoApprove(Request $request, Order $order): RedirectResponse
    {
        $this->orders->undoApprove($order, Auth::user(), $request->input('note'));

        return $this->backAfterOrderChange($order->fresh(), 'Approval undone. Order is pending again and stock was re-reserved.');
    }

    public function unmarkPaid(Request $request, Order $order): RedirectResponse
    {
        $this->orders->unmarkPaid($order, Auth::user(), $request->input('note'));

        return $this->backAfterOrderChange($order->fresh(), 'Payment unmarked.');
    }

    protected function backAfterOrderChange(Order $order, string $message): RedirectResponse
    {
        return back()
            ->with('success', $message)
            ->with('refresh_orders_list', true)
            ->with('orders_list_after_change', $this->listForOrder($order));
    }

    protected function listForOrder(Order $order): string
    {
        if (in_array($order->status->value, $this->closedOrderStatuses(), true)) {
            return 'closed';
        }

        if (
            $order->status === OrderStatus::Delivered
            && $order->payment_status === PaymentStatus::Paid
        ) {
            return 'finished';
        }

        return 'active';
    }

    protected function filteredOrdersQuery(Request $request)
    {
        $list = $this->ordersList($request);
        $closedStatuses = $this->closedOrderStatuses();

        return Order::query()
            ->when($list === 'closed', fn ($q) => $q->whereIn('status', $closedStatuses))
            ->when($list === 'finished', function ($q) {
                $q->where('status', OrderStatus::Delivered)
                    ->where('payment_status', PaymentStatus::Paid);
            })
            ->when($list === 'active', function ($q) use ($closedStatuses) {
                $q->whereNotIn('status', $closedStatuses)
                    ->where(function ($inner) {
                        $inner->where('payment_status', '!=', PaymentStatus::Paid)
                            ->orWhere('status', '!=', OrderStatus::Delivered);
                    });
            })
            ->when($request->status, function ($q, $s) use ($list) {
                $allowed = collect($this->statusesForList($list))->map(fn (OrderStatus $status) => $status->value);
                if ($allowed->contains($s)) {
                    $q->where('status', $s);
                }
            })
            ->when($request->q, function ($q) use ($request) {
                $term = $request->q;
                $q->where(function ($inner) use ($term) {
                    $inner->where('order_number', 'like', "%{$term}%")
                        ->orWhere('customer_email', 'like', "%{$term}%")
                        ->orWhere('customer_name', 'like', "%{$term}%")
                        ->orWhere('customer_phone', 'like', "%{$term}%");
                });
            });
    }

    protected function ordersList(Request $request): string
    {
        $list = $request->string('list')->toString();

        return in_array($list, ['finished', 'closed'], true) ? $list : 'active';
    }

    /**
     * Statuses that can appear in each orders list tab.
     *
     * @return list<OrderStatus>
     */
    protected function statusesForList(string $list): array
    {
        return match ($list) {
            'finished' => [
                OrderStatus::Delivered,
            ],
            'closed' => [
                OrderStatus::Rejected,
                OrderStatus::Cancelled,
                OrderStatus::Returned,
                OrderStatus::Refunded,
            ],
            default => [
                OrderStatus::PendingApproval,
                OrderStatus::Approved,
                OrderStatus::Preparing,
                OrderStatus::OnTheWay,
                OrderStatus::Delivered,
            ],
        };
    }

    /** @return list<string> */
    protected function closedOrderStatuses(): array
    {
        return [
            OrderStatus::Rejected->value,
            OrderStatus::Cancelled->value,
            OrderStatus::Refunded->value,
            OrderStatus::Returned->value,
        ];
    }
}
