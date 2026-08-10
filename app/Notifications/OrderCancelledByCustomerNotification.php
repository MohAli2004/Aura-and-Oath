<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderCancelledByCustomerNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Order '.$this->order->order_number.' cancelled by customer')
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->order->customer_name.' cancelled order '.$this->order->order_number.'.')
            ->line('Total: '.money($this->order->total))
            ->action('View order', url('/admin/orders/'.$this->order->id));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Order cancelled by customer',
            'message' => $this->order->customer_name.' cancelled order '.$this->order->order_number.' ('.money($this->order->total).').',
            'url' => route('admin.orders.show', $this->order),
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'total' => $this->order->total,
            'for_admin' => true,
        ];
    }
}
