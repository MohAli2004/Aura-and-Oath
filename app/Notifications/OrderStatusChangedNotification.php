<?php

namespace App\Notifications;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Order $order,
        public OrderStatus $from,
        public OrderStatus $to
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Order '.$this->order->order_number.' update')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('Your order status changed from '.$this->from->label().' to '.$this->to->label().'.')
            ->action('View order', url('/account/orders/'.$this->order->id));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Order '.$this->to->label(),
            'message' => 'Order '.$this->order->order_number.' updated from '.$this->from->label().' to '.$this->to->label().'.',
            'url' => route('account.orders.show', $this->order),
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'from' => $this->from->value,
            'to' => $this->to->value,
        ];
    }
}
