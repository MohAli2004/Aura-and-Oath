<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Notifications\ContactMessageNotification;
use App\Notifications\LowStockNotification;
use App\Notifications\NewUserRegisteredNotification;
use App\Notifications\OrderCancelledByCustomerNotification;
use App\Notifications\OrderPaymentStatusChangedNotification;
use App\Notifications\OrderPlacedNotification;
use App\Notifications\OrderReturnRequestedNotification;
use App\Notifications\OrderStatusChangedNotification;
use App\Notifications\OrderUpdatedNotification;
use App\Notifications\WishPaymentReceivedNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class NotificationService
{
    public function notifyOrderPlaced(Order $order): void
    {
        $this->safe(function () use ($order) {
            if ($order->user) {
                $order->user->notify(new OrderPlacedNotification($order));
            }

            Notification::send($this->activeAdmins(), new OrderPlacedNotification($order, forAdmin: true));
        }, 'order.placed', ['order_id' => $order->id]);
    }

    public function notifyOrderStatusChanged(Order $order, OrderStatus $from, OrderStatus $to): void
    {
        $this->safe(function () use ($order, $from, $to) {
            if ($order->user) {
                $order->user->notify(new OrderStatusChangedNotification($order, $from, $to));
            }
        }, 'order.status_changed', ['order_id' => $order->id]);
    }

    public function notifyOrderPaymentStatusChanged(Order $order, PaymentStatus $from, PaymentStatus $to): void
    {
        if ($from === $to) {
            return;
        }

        $this->safe(function () use ($order, $from, $to) {
            if ($order->user) {
                $order->user->notify(new OrderPaymentStatusChangedNotification($order, $from, $to));
            }
        }, 'order.payment_status_changed', ['order_id' => $order->id]);
    }

    public function notifyOrderUpdated(Order $order, string $summary): void
    {
        $this->safe(function () use ($order, $summary) {
            if ($order->user) {
                $order->user->notify(new OrderUpdatedNotification($order, $summary));
            }
        }, 'order.updated', ['order_id' => $order->id]);
    }

    public function notifyAdminsOrderCancelledByCustomer(Order $order): void
    {
        $this->safe(function () use ($order) {
            Notification::send(
                $this->activeAdmins(),
                new OrderCancelledByCustomerNotification($order)
            );
        }, 'order.cancelled_by_customer', ['order_id' => $order->id]);
    }

    public function notifyAdminsReturnRequested(Order $order, ReturnRequest $returnRequest): void
    {
        $this->safe(function () use ($order, $returnRequest) {
            Notification::send(
                $this->activeAdmins(),
                new OrderReturnRequestedNotification($order, $returnRequest)
            );
        }, 'order.return_requested', ['order_id' => $order->id]);
    }

    public function notifyAdminsNewUser(User $user, string $method = 'email'): void
    {
        if (! $user->isCustomer()) {
            return;
        }

        $this->safe(function () use ($user, $method) {
            Notification::send(
                $this->activeAdmins(),
                new NewUserRegisteredNotification($user, $method)
            );
        }, 'user.registered', ['user_id' => $user->id]);
    }

    public function notifyLowStock(Product $product): void
    {
        $this->safe(function () use ($product) {
            Notification::send($this->activeAdmins(), new LowStockNotification($product));
        }, 'product.low_stock', ['product_id' => $product->id]);
    }

    public function notifyContactMessage(string $name, string $email, string $message): void
    {
        $this->safe(function () use ($name, $email, $message) {
            Notification::send(
                $this->activeAdmins(),
                new ContactMessageNotification($name, $email, $message)
            );
        }, 'contact.message');
    }

    public function notifyAdminWishPayment(Order $order): void
    {
        $this->safe(function () use ($order) {
            Notification::send($this->activeAdmins(), new WishPaymentReceivedNotification($order));

            $message = sprintf(
                '%s: Wish payment received for %s. Amount %s.',
                config('aura.name', 'Aura & Oath'),
                $order->order_number,
                money($order->total)
            );

            app(WhatsAppService::class)->notifyAdmin($message);
        }, 'order.wish_payment', ['order_id' => $order->id]);
    }

    /**
     * Placeholder for future SMS gateway integration.
     */
    public function sendSms(string $phone, string $message): bool
    {
        // Structure ready for Twilio / local SMS provider.
        logger()->info('SMS placeholder', compact('phone', 'message'));

        return false;
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, User> */
    protected function activeAdmins()
    {
        return User::query()->where('role', 'admin')->where('is_active', true)->get();
    }

    protected function safe(callable $callback, string $context, array $extra = []): void
    {
        try {
            $callback();
        } catch (Throwable $e) {
            Log::warning('Notification failed: '.$context, array_merge($extra, [
                'error' => $e->getMessage(),
            ]));
        }
    }
}
