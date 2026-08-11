<?php

namespace App\Services;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
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
        return $this->transition($order, OrderStatus::Preparing, $admin, $note, function (Order $order) use ($admin) {
            $order->load('items.product', 'items.variant');

            $accepted = $order->items->filter(fn (OrderItem $item) => $item->isAccepted());

            if ($accepted->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Cannot approve an order with no remaining items. Restore an item or reject the order.',
                ]);
            }

            foreach ($accepted as $item) {
                if ($item->product?->track_inventory) {
                    $this->inventoryService->convertReservationToSale(
                        $item->product,
                        $item->quantity,
                        $item->variant,
                        $order,
                        $admin
                    );
                }

                $item->status = OrderItemStatus::Approved;
                $item->save();
            }

            $this->recalculateTotals($order);

            $order->approved_at = now();
            $order->approved_by = $admin->id;
            $order->save();
        });
    }

    /**
     * Reject selected line items while the order is still pending approval.
     *
     * @param  list<array{id:int|string, reason?:string|null}>  $rejects
     */
    public function rejectItems(Order $order, User $admin, array $rejects): Order
    {
        return DB::transaction(function () use ($order, $admin, $rejects) {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->status !== OrderStatus::PendingApproval) {
                throw ValidationException::withMessages([
                    'status' => 'Items can only be rejected while the order is pending approval.',
                ]);
            }

            $order->load('items.product', 'items.variant');
            $byId = collect($rejects)->keyBy(fn ($row) => (int) $row['id']);

            if ($byId->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Select at least one item to reject.',
                ]);
            }

            $rejectedNames = [];

            foreach ($order->items as $item) {
                if (! $byId->has($item->id) || $item->isRejected()) {
                    continue;
                }

                if ($item->product?->track_inventory) {
                    $this->inventoryService->release(
                        $item->product,
                        $item->quantity,
                        $item->variant,
                        $order,
                        $admin
                    );
                }

                $reason = trim((string) ($byId[$item->id]['reason'] ?? '')) ?: null;
                $item->status = OrderItemStatus::Rejected;
                $item->rejection_reason = $reason;
                $item->rejected_at = now();
                $item->save();

                $rejectedNames[] = $item->product_name.($item->variant_name ? ' — '.$item->variant_name : '');
            }

            if ($rejectedNames === []) {
                throw ValidationException::withMessages([
                    'items' => 'No eligible items were rejected.',
                ]);
            }

            $this->recalculateTotals($order);

            $this->addNote(
                $order,
                $admin,
                'Rejected item(s): '.implode(', ', $rejectedNames).'. Totals recalculated.',
                true
            );

            $this->audit->log(
                'order.items_rejected',
                $order,
                null,
                ['item_ids' => $byId->keys()->all()],
                'Partial item rejection',
                $admin
            );

            $remaining = $order->items()->accepted()->count();

            if ($remaining === 0) {
                return $this->reject(
                    $order->fresh(),
                    $admin,
                    'All items were rejected.'
                );
            }

            $fresh = $order->fresh(['items', 'statusHistories', 'notes', 'user']);
            $this->notifications->notifyOrderUpdated(
                $fresh,
                'Some items on order '.$fresh->order_number.' were unavailable and removed: '
                .implode(', ', $rejectedNames)
                .'. Updated total: '.money($fresh->total).'.'
            );

            return $fresh;
        });
    }

    public function restoreItem(Order $order, OrderItem $item, User $admin): Order
    {
        return DB::transaction(function () use ($order, $item, $admin) {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $item = OrderItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();

            if ((int) $item->order_id !== (int) $order->id) {
                throw ValidationException::withMessages([
                    'item' => 'Item does not belong to this order.',
                ]);
            }

            if ($order->status !== OrderStatus::PendingApproval) {
                throw ValidationException::withMessages([
                    'status' => 'Rejected items can only be restored while the order is pending approval.',
                ]);
            }

            if (! $item->isRejected()) {
                return $order->fresh(['items']);
            }

            $item->load(['product', 'variant']);

            if ($item->product?->track_inventory) {
                $this->inventoryService->reserve(
                    $item->product,
                    $item->quantity,
                    $item->variant,
                    $order,
                    $admin
                );
            }

            $item->status = OrderItemStatus::Pending;
            $item->rejection_reason = null;
            $item->rejected_at = null;
            $item->save();

            $this->recalculateTotals($order);

            $this->addNote(
                $order,
                $admin,
                'Restored item: '.$item->product_name.($item->variant_name ? ' — '.$item->variant_name : '').'. Totals recalculated.',
                true
            );

            $this->audit->log(
                'order.item_restored',
                $order,
                null,
                ['item_id' => $item->id],
                'Rejected item restored',
                $admin
            );

            return $order->fresh(['items', 'notes']);
        });
    }

    public function updateItemQuantity(Order $order, OrderItem $item, int $quantity, User $admin, ?string $note = null): Order
    {
        return DB::transaction(function () use ($order, $item, $quantity, $admin, $note) {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $item = OrderItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();

            if ((int) $item->order_id !== (int) $order->id) {
                throw ValidationException::withMessages([
                    'item' => 'Item does not belong to this order.',
                ]);
            }

            if ($order->status !== OrderStatus::PendingApproval) {
                throw ValidationException::withMessages([
                    'status' => 'Quantities can only be edited while the order is pending approval.',
                ]);
            }

            if ($item->isRejected()) {
                throw ValidationException::withMessages([
                    'quantity' => 'Rejected items cannot have their quantity changed. Restore the item first.',
                ]);
            }

            if ($quantity < 1) {
                throw ValidationException::withMessages([
                    'quantity' => 'Quantity must be at least 1. Reject the item to remove it.',
                ]);
            }

            $fromQty = (int) $item->quantity;
            if ($fromQty === $quantity) {
                return $order->fresh(['items']);
            }

            $item->load(['product', 'variant']);
            $delta = $quantity - $fromQty;

            if ($item->product?->track_inventory) {
                try {
                    if ($delta > 0) {
                        $this->inventoryService->reserve(
                            $item->product,
                            $delta,
                            $item->variant,
                            $order,
                            $admin
                        );
                    } else {
                        $this->inventoryService->release(
                            $item->product,
                            abs($delta),
                            $item->variant,
                            $order,
                            $admin
                        );
                    }
                } catch (\RuntimeException $e) {
                    throw ValidationException::withMessages([
                        'quantity' => $e->getMessage(),
                    ]);
                }
            }

            $item->quantity = $quantity;
            $item->line_total = round((float) $item->unit_price * $quantity, 2);
            $item->save();

            $this->recalculateTotals($order);

            $label = $item->product_name.($item->variant_name ? ' — '.$item->variant_name : '');
            $summary = sprintf(
                'Quantity for %s changed from %d to %d on order %s. New total: %s.',
                $label,
                $fromQty,
                $quantity,
                $order->order_number,
                money($order->fresh()->total)
            );

            if (filled($note)) {
                $summary .= ' Note: '.trim($note);
            }

            $this->addNote($order, $admin, $summary, true);

            $this->audit->log(
                'order.item_quantity_updated',
                $order,
                ['item_id' => $item->id, 'quantity' => $fromQty],
                ['item_id' => $item->id, 'quantity' => $quantity],
                $note,
                $admin
            );

            $fresh = $order->fresh(['items', 'notes', 'user']);
            $this->notifications->notifyOrderUpdated($fresh, $summary);

            return $fresh;
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
        $order = $this->transition($order, OrderStatus::Cancelled, $actor, $note, function (Order $order) use ($actor) {
            if ($order->status === OrderStatus::PendingApproval) {
                $this->releaseReservations($order, $actor);
            }
            $order->cancelled_at = now();
            $order->save();
        });

        if ($actor->isCustomer()) {
            $this->notifications->notifyAdminsOrderCancelledByCustomer($order);
        }

        return $order;
    }

    public function markPaid(Order $order, ?User $admin = null, ?string $note = null): Order
    {
        if ($order->payment_status === PaymentStatus::Paid) {
            return $order;
        }

        if (in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Refunded, OrderStatus::Rejected], true)) {
            throw ValidationException::withMessages([
                'payment_status' => 'Cancelled, refunded, or rejected orders cannot be marked as paid.',
            ]);
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

            $fresh = $order->fresh();
            $this->notifications->notifyOrderPaymentStatusChanged(
                $fresh,
                $from,
                PaymentStatus::Paid
            );

            return $fresh;
        });
    }

    public function undoApprove(Order $order, User $admin, ?string $note = null): Order
    {
        return DB::transaction(function () use ($order, $admin, $note) {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! in_array($order->status, [OrderStatus::Preparing, OrderStatus::Approved], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Only preparing (approved) orders can be moved back to pending approval.',
                ]);
            }

            $from = $order->status;
            $order->load('items.product', 'items.variant');

            foreach ($order->items as $item) {
                if ($item->isRejected()) {
                    continue;
                }

                if ($item->product?->track_inventory) {
                    $this->inventoryService->revertSaleToReservation(
                        $item->product,
                        $item->quantity,
                        $item->variant,
                        $order,
                        $admin
                    );
                }

                $item->status = OrderItemStatus::Pending;
                $item->save();
            }

            $order->status = OrderStatus::PendingApproval;
            $order->approved_at = null;
            $order->approved_by = null;
            $order->save();

            OrderStatusHistory::query()->create([
                'order_id' => $order->id,
                'from_status' => $from,
                'to_status' => OrderStatus::PendingApproval,
                'changed_by' => $admin->id,
                'note' => $note ?: 'Approval undone by admin.',
                'is_customer_visible' => true,
            ]);

            $this->audit->log(
                'order.undo_approve',
                $order,
                ['status' => $from->value],
                ['status' => OrderStatus::PendingApproval->value],
                $note,
                $admin
            );

            $this->notifications->notifyOrderStatusChanged(
                $order->fresh(),
                $from,
                OrderStatus::PendingApproval
            );

            return $order->fresh(['items', 'statusHistories']);
        });
    }

    public function unmarkPaid(Order $order, User $admin, ?string $note = null): Order
    {
        if ($order->payment_status !== PaymentStatus::Paid) {
            throw ValidationException::withMessages([
                'payment_status' => 'Only paid orders can have payment unmarked.',
            ]);
        }

        if (in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Refunded, OrderStatus::Rejected], true)) {
            throw ValidationException::withMessages([
                'payment_status' => 'Cancelled, refunded, or rejected orders cannot have payment changed.',
            ]);
        }

        return DB::transaction(function () use ($order, $admin, $note) {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->payment_status !== PaymentStatus::Paid) {
                throw ValidationException::withMessages([
                    'payment_status' => 'Only paid orders can have payment unmarked.',
                ]);
            }

            $from = $order->payment_status;
            $to = $order->payment_method->requiresTransferConfirmation()
                ? PaymentStatus::AwaitingConfirmation
                : PaymentStatus::Pending;

            $order->payment_status = $to;
            $order->save();

            $this->addNote(
                $order,
                $admin,
                $note ?: 'Payment unmarked — set back to '.$to->label().'.',
                true
            );

            $this->audit->log(
                'order.payment_unmarked',
                $order,
                ['payment_status' => $from->value],
                ['payment_status' => $to->value],
                $note,
                $admin
            );

            $fresh = $order->fresh();
            $this->notifications->notifyOrderPaymentStatusChanged($fresh, $from, $to);

            return $fresh;
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
                    if ($item->isRejected()) {
                        continue;
                    }
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

    protected function recalculateTotals(Order $order): void
    {
        $order->loadMissing(['items', 'coupon']);

        $previousSubtotal = (float) $order->subtotal;
        $previousDiscount = (float) $order->discount_amount;

        $acceptedSubtotal = round((float) $order->items
            ->filter(fn (OrderItem $item) => $item->isAccepted())
            ->sum(fn (OrderItem $item) => (float) $item->line_total), 2);

        if ($order->coupon) {
            $discount = app(CouponService::class)->calculateDiscount($order->coupon, $acceptedSubtotal);
            if ($order->coupon->min_order_amount !== null && $acceptedSubtotal < (float) $order->coupon->min_order_amount) {
                $discount = 0.0;
            }
        } elseif ($previousSubtotal > 0 && $previousDiscount > 0) {
            $discount = round($previousDiscount * ($acceptedSubtotal / $previousSubtotal), 2);
        } else {
            $discount = 0.0;
        }

        $discount = min($discount, $acceptedSubtotal);
        $taxRate = (float) setting('tax_rate', 0);
        $taxable = max(0, $acceptedSubtotal - $discount);
        $tax = round($taxable * ($taxRate / 100), 2);
        $delivery = $acceptedSubtotal > 0 ? (float) $order->delivery_fee : 0.0;
        $total = round($taxable + $delivery + $tax, 2);

        $order->subtotal = $acceptedSubtotal;
        $order->discount_amount = $discount;
        $order->delivery_fee = $delivery;
        $order->tax_amount = $tax;
        $order->total = $total;
        $order->save();
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
            if ($item->isRejected()) {
                continue;
            }

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
