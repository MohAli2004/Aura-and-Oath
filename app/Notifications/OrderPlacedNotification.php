<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderPlacedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Order $order,
        public bool $forAdmin = false
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->forAdmin
                ? 'New order '.$this->order->order_number
                : 'We received your order '.$this->order->order_number)
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->forAdmin
                ? 'A new order is awaiting approval.'
                : 'Thank you for shopping with '.config('aura.name').'. Your order is pending approval.')
            ->line('Order: '.$this->order->order_number)
            ->line('Total: '.money($this->order->total));

        if ($this->forAdmin) {
            $message->action('Review order', url('/admin/orders/'.$this->order->id));
        } else {
            $message->action('View order', url('/account/orders/'.$this->order->id));
        }

        return $message;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'total' => $this->order->total,
            'for_admin' => $this->forAdmin,
        ];
    }
}
