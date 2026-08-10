<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Notifications\ContactMessageNotification;
use App\Notifications\LowStockNotification;
use App\Notifications\OrderPlacedNotification;
use App\Notifications\OrderStatusChangedNotification;
use App\Notifications\WishPaymentReceivedNotification;
use Illuminate\Support\Facades\Notification;

class NotificationService
{
    public function notifyOrderPlaced(Order $order): void
    {
        if ($order->user) {
            $order->user->notify(new OrderPlacedNotification($order));
        }

        Notification::send($this->activeAdmins(), new OrderPlacedNotification($order, forAdmin: true));
    }

    public function notifyOrderStatusChanged(Order $order, OrderStatus $from, OrderStatus $to): void
    {
        if ($order->user) {
            $order->user->notify(new OrderStatusChangedNotification($order, $from, $to));
        }
    }

    public function notifyLowStock(Product $product): void
    {
        Notification::send($this->activeAdmins(), new LowStockNotification($product));
    }

    public function notifyContactMessage(string $name, string $email, string $message): void
    {
        Notification::send(
            $this->activeAdmins(),
            new ContactMessageNotification($name, $email, $message)
        );
    }

    public function notifyAdminWishPayment(Order $order): void
    {
        Notification::send($this->activeAdmins(), new WishPaymentReceivedNotification($order));

        $message = sprintf(
            '%s: Wish payment received for %s. Amount %s.',
            config('aura.name', 'Aura & Oath'),
            $order->order_number,
            money($order->total)
        );

        app(WhatsAppService::class)->notifyAdmin($message);
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
}
