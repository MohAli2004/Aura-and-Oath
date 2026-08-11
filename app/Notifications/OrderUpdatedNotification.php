<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Order $order,
        public string $summary
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Update on order '.$this->order->order_number)
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->summary)
            ->line('Updated total: '.money($this->order->total))
            ->action('View order', url('/account/orders/'.$this->order->id));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Order updated',
            'message' => $this->summary,
            'url' => route('account.orders.show', $this->order),
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
        ];
    }
}
