<?php

namespace App\Notifications;

use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderPaymentStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Order $order,
        public PaymentStatus $from,
        public PaymentStatus $to
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
            ->subject('Order '.$this->order->order_number.' payment update')
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->body())
            ->action('View order', url('/account/orders/'.$this->order->id));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'message' => $this->body(),
            'url' => route('account.orders.show', $this->order),
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'from' => $this->from->value,
            'to' => $this->to->value,
        ];
    }

    protected function title(): string
    {
        if ($this->from === PaymentStatus::Paid && $this->to !== PaymentStatus::Paid) {
            return 'Payment unmarked';
        }

        if ($this->to === PaymentStatus::Paid) {
            return 'Payment confirmed';
        }

        return 'Payment status updated';
    }

    protected function body(): string
    {
        if ($this->from === PaymentStatus::Paid && $this->to !== PaymentStatus::Paid) {
            return 'Payment for order '.$this->order->order_number.' was unmarked. It is now '.$this->to->label().'.';
        }

        if ($this->to === PaymentStatus::Paid) {
            return 'Payment for order '.$this->order->order_number.' was marked as paid.';
        }

        return 'Payment for order '.$this->order->order_number.' changed from '.$this->from->label().' to '.$this->to->label().'.';
    }
}
