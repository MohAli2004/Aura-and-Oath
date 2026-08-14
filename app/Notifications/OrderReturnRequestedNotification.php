<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\ReturnRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderReturnRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Order $order,
        public ReturnRequest $returnRequest
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
            ->subject('Return requested for order '.$this->order->order_number)
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->order->customer_name.' requested a return for order '.$this->order->order_number.'.')
            ->line('Reason: '.$this->returnRequest->reason)
            ->action('Review return', url('/admin/orders/'.$this->order->id));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Return requested',
            'message' => $this->order->customer_name.' requested a return for order '.$this->order->order_number.'.',
            'url' => route('admin.orders.show', $this->order),
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'return_request_id' => $this->returnRequest->id,
            'for_admin' => true,
        ];
    }
}
