<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderNote;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected AuditService $audit,
        protected NotificationService $notifications
    ) {}

    public function approve(Order $order, User $admin, ?string $note = null): Order
    {
        return $this->transition($order, OrderStatus::Approved, $admin, $note, function (Order $order) use ($admin) {
            $order->load('items.product', 'items.variant');

            foreach ($order->items as $item) {
                if (! $item->product) {
                    continue;
                }
                if ($item->product->track_inventory) {
                    $this->inventoryService->convertReservationToSale(
                        $item->product,
                        $item->quantity,
                        $item->variant,
                        $order,
                        $admin
                    );
                }
            }

            $order->approved_at = now();
            $order->approved_by = $admin->id;
            $order->save();
        });
    }

    public function reject(Order $order, User $admin, string $reason): Order
    {
        return $this->transition($order, OrderStatus::Rejected, $admin, $reason, function (Order $order) use ($admin, $reason) {
            $this->releaseReservations($order, $admin);
            $order->rejection_reason = $reason;
            $order->cancelled_at = now();
            $order->save();
        });
    }

    public function cancel(Order $order, User $actor, ?string $note = null): Order
    {
        return $this->transition($order, OrderStatus::Cancelled, $actor, $note, function (Order $order) use ($actor) {
            if ($order->status === OrderStatus::PendingApproval) {
                $this->releaseReservations($order, $actor);
            }
            // If already approved, stock was deducted — do not auto-restore unless returned.
            $order->cancelled_at = now();
            $order->save();
        });
    }

    public function markPaid(Order $order, ?User $admin = null, ?string $note = null): Order
    {
        if ($order->payment_status === PaymentStatus::Paid) {
            return $order;
        }

        return DB::transaction(function () use ($order, $admin, $note) {
            $from = $order->payment_status;
            $order->payment_status = PaymentStatus::Paid;
            $order->save();

            $actor = $admin ?? User::query()->where('role', \App\Enums\UserRole::Admin)->where('is_active', true)->first();

            if ($actor) {
                $this->addNote(
                    $order,
                    $actor,
                    $note ?: 'Payment confirmed ('.$order->payment_method->label().').',
                    true
                );
            }

            $this->audit->log(
                'order.payment_paid',
                $order,
                ['payment_status' => $from->value],
                ['payment_status' => PaymentStatus::Paid->value],
                user: $actor
            );

            return $order->fresh();
        });
    }

    public function updateStatus(Order $order, OrderStatus $status, User $admin, ?string $note = null, bool $customerVisible = true): Order
    {
        return $this->transition($order, $status, $admin, $note, function (Order $order) use ($status) {
            match ($status) {
                OrderStatus::OnTheWay => $order->shipped_at = now(),
                OrderStatus::Delivered => $order->delivered_at = now(),
                OrderStatus::Cancelled => $order->cancelled_at = now(),
                OrderStatus::Refunded => $order->payment_status = PaymentStatus::Refunded,
                default => null,
            };
            $order->save();
        }, $customerVisible);
    }

    public function confirmReturnResellable(Order $order, User $admin, bool $resellable = true, ?string $note = null): Order
    {
        return DB::transaction(function () use ($order, $admin, $resellable, $note) {
            $order = $this->updateStatus($order, OrderStatus::Returned, $admin, $note);

            if ($resellable) {
                $order->load('items.product', 'items.variant');
                foreach ($order->items as $item) {
                    if ($item->product?->track_inventory) {
                        $this->inventoryService->restore(
                            $item->product,
                            $item->quantity,
                            $item->variant,
                            $order,
                            $admin,
                            'Resellable return'
                        );
                    }
                }
            }

            return $order->fresh();
        });
    }

    public function addNote(Order $order, User $user, string $body, bool $customerVisible = false): OrderNote
    {
        $note = OrderNote::query()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'body' => $body,
            'is_customer_visible' => $customerVisible,
        ]);

        $this->audit->log('order.note', $order, null, ['note_id' => $note->id], user: $user);

        return $note;
    }

    protected function transition(
        Order $order,
        OrderStatus $to,
        User $actor,
        ?string $note,
        ?callable $after = null,
        bool $customerVisible = true
    ): Order {
        return DB::transaction(function () use ($order, $to, $actor, $note, $after, $customerVisible) {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $from = $order->status;

            if ($from === $to) {
                return $order;
            }

            if (! $from->canTransitionTo($to)) {
                throw ValidationException::withMessages([
                    'status' => "Cannot transition from {$from->label()} to {$to->label()}.",
                ]);
            }

            if ($after) {
                $after($order);
            }

            $order->status = $to;
            $order->save();

            OrderStatusHistory::query()->create([
                'order_id' => $order->id,
                'from_status' => $from,
                'to_status' => $to,
                'changed_by' => $actor->id,
                'note' => $note,
                'is_customer_visible' => $customerVisible,
            ]);

            $this->audit->log(
                'order.status',
                $order,
                ['status' => $from->value],
                ['status' => $to->value],
                $note,
                $actor
            );

            $this->notifications->notifyOrderStatusChanged($order->fresh(), $from, $to);

            return $order->fresh(['items', 'statusHistories']);
        });
    }

    protected function releaseReservations(Order $order, User $actor): void
    {
        $order->load('items.product', 'items.variant');

        foreach ($order->items as $item) {
            if ($item->product?->track_inventory) {
                $this->inventoryService->release(
                    $item->product,
                    $item->quantity,
                    $item->variant,
                    $order,
                    $actor
                );
            }
        }
    }
}
