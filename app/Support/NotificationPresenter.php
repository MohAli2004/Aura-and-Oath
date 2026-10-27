<?php

namespace App\Support;

use App\Models\Order;
use App\Notifications\ContactMessageNotification;
use App\Notifications\OrderDeliveryConfirmationNotification;
use App\Notifications\LowStockNotification;
use App\Notifications\NewUserRegisteredNotification;
use App\Notifications\OrderCancelledByCustomerNotification;
use App\Notifications\OrderPaymentStatusChangedNotification;
use App\Notifications\OrderPlacedNotification;
use App\Notifications\OrderReturnRequestedNotification;
use App\Notifications\OrderStatusChangedNotification;
use App\Notifications\OrderUpdatedNotification;
use App\Notifications\WishPaymentReceivedNotification;
use Illuminate\Notifications\DatabaseNotification;

class NotificationPresenter
{
    /**
     * @return array{id:string,title:string,message:string,url:?string,unread:bool,created_at:string,created_at_iso:string}
     */
    public static function present(DatabaseNotification $notification): array
    {
        $data = $notification->data;

        return [
            'id' => (string) $notification->id,
            'title' => (string) ($data['title'] ?? self::fallbackTitle($notification)),
            'message' => (string) ($data['message'] ?? self::fallbackMessage($notification)),
            // Notification payloads are rendered as links and used as redirect
            // targets, so only same-site URLs are allowed through.
            'url' => isset($data['url']) && is_string($data['url']) && $data['url'] !== ''
                ? safe_url($data['url'], self::fallbackUrl($notification) ?? '/')
                : self::fallbackUrl($notification),
            'action' => self::confirmAction($data),
            'unread' => $notification->read_at === null,
            'created_at' => $notification->created_at?->diffForHumans() ?? '',
            'created_at_iso' => $notification->created_at?->toIso8601String() ?? '',
        ];
    }

    /**
     * A one-tap "I still want my order" button, shown only while the customer's window is open.
     *
     * @param  array<string, mixed>  $data
     * @return array{label:string,url:string}|null
     */
    protected static function confirmAction(array $data): ?array
    {
        if (empty($data['delivery_confirmation']) || empty($data['order_id'])) {
            return null;
        }

        $order = Order::query()->find($data['order_id']);
        if (! $order || ! $order->hasPendingDeliveryConfirmation()) {
            return null;
        }

        return [
            'label' => __('storefront.delivery_confirm_button'),
            'url' => route('account.orders.delivery.confirm', $order),
        ];
    }

    protected static function fallbackTitle(DatabaseNotification $notification): string
    {
        return match ($notification->type) {
            OrderPlacedNotification::class => ! empty($notification->data['for_admin'])
                ? 'New order placed'
                : 'Order received',
            OrderStatusChangedNotification::class => 'Order status updated',
            OrderUpdatedNotification::class => 'Order updated',
            OrderDeliveryConfirmationNotification::class => 'Delivery charge update',
            OrderPaymentStatusChangedNotification::class => 'Payment status updated',
            OrderCancelledByCustomerNotification::class => 'Order cancelled by customer',
            OrderReturnRequestedNotification::class => 'Return requested',
            NewUserRegisteredNotification::class => 'New customer registered',
            LowStockNotification::class => 'Low stock alert',
            ContactMessageNotification::class => 'New contact message',
            WishPaymentReceivedNotification::class => 'Wish payment received',
            default => 'Notification',
        };
    }

    protected static function fallbackMessage(DatabaseNotification $notification): string
    {
        $data = $notification->data;

        return match ($notification->type) {
            OrderPlacedNotification::class => isset($data['order_number'])
                ? 'Order '.$data['order_number'].(isset($data['total']) ? ' · '.money($data['total']) : '')
                : 'A new order was placed.',
            OrderStatusChangedNotification::class => isset($data['order_number'], $data['to'])
                ? 'Order '.$data['order_number'].' is now '.str_replace('_', ' ', (string) $data['to']).'.'
                : 'Your order status was updated.',
            OrderUpdatedNotification::class => isset($data['message'])
                ? (string) $data['message']
                : (isset($data['order_number'])
                    ? 'Order '.$data['order_number'].' was updated.'
                    : 'Your order was updated.'),
            OrderPaymentStatusChangedNotification::class => isset($data['order_number'], $data['to'])
                ? 'Payment for order '.$data['order_number'].' is now '.str_replace('_', ' ', (string) $data['to']).'.'
                : 'Your payment status was updated.',
            OrderCancelledByCustomerNotification::class => isset($data['order_number'])
                ? 'Customer cancelled order '.$data['order_number'].'.'
                : 'A customer cancelled an order.',
            OrderReturnRequestedNotification::class => isset($data['order_number'])
                ? 'Customer requested a return for order '.$data['order_number'].'.'
                : 'A customer requested a return.',
            NewUserRegisteredNotification::class => isset($data['name'], $data['email'])
                ? $data['name'].' ('.$data['email'].') registered.'
                : 'A new customer registered.',
            LowStockNotification::class => isset($data['sku'])
                ? 'Product '.$data['sku'].' is running low'.(isset($data['available']) ? ' ('.$data['available'].' left)' : '').'.'
                : 'A product is low on stock.',
            ContactMessageNotification::class => ($data['sender_name'] ?? 'Customer').' sent a contact message.',
            WishPaymentReceivedNotification::class => isset($data['order_number'])
                ? 'Payment confirmed for '.$data['order_number'].'.'
                : 'A Wish payment was received.',
            default => 'You have a new notification.',
        };
    }

    protected static function fallbackUrl(DatabaseNotification $notification): ?string
    {
        $data = $notification->data;
        $forAdmin = ! empty($data['for_admin']);

        return match ($notification->type) {
            OrderPlacedNotification::class, WishPaymentReceivedNotification::class, OrderCancelledByCustomerNotification::class, OrderReturnRequestedNotification::class => isset($data['order_id'])
                ? ($forAdmin || in_array($notification->type, [WishPaymentReceivedNotification::class, OrderCancelledByCustomerNotification::class, OrderReturnRequestedNotification::class], true)
                    ? url('/admin/orders/'.$data['order_id'])
                    : url('/account/orders/'.$data['order_id']))
                : null,
            OrderStatusChangedNotification::class, OrderPaymentStatusChangedNotification::class, OrderUpdatedNotification::class, OrderDeliveryConfirmationNotification::class => isset($data['order_id'])
                ? url('/account/orders/'.$data['order_id'])
                : null,
            NewUserRegisteredNotification::class => isset($data['user_id'])
                ? url('/admin/customers/'.$data['user_id'])
                : url('/admin/customers'),
            LowStockNotification::class => url('/admin/inventory'),
            ContactMessageNotification::class => url('/admin/notifications'),
            default => null,
        };
    }
}
