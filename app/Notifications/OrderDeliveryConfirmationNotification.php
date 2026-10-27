<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Stages: requested (customer must confirm), auto_confirmed (customer), confirmed (admins).
 */
class OrderDeliveryConfirmationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Order $order,
        public string $stage,
        public string $summary,
        public bool $forAdmin = false
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title().' — order '.$this->order->order_number)
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->summary);

        if ($this->stage === 'requested') {
            $mail->line('Please confirm that you still want this order. If we do not hear from you by '
                .$this->order->delivery_confirmation_due_at?->format('M j, H:i')
                .', your order will carry on as normal with the new delivery charge.');

            return $mail->action('Confirm my order', $this->url());
        }

        return $mail->action('View order', $this->url());
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'message' => $this->summary,
            'url' => $this->url(),
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'stage' => $this->stage,
            'delivery_confirmation' => $this->stage === 'requested',
            'for_admin' => $this->forAdmin,
        ];
    }

    protected function title(): string
    {
        return match ($this->stage) {
            'requested' => 'Please confirm your order',
            'auto_confirmed' => 'Your order is confirmed',
            default => 'Customer confirmed the delivery charge',
        };
    }

    protected function url(): string
    {
        return $this->forAdmin
            ? route('admin.orders.show', $this->order)
            : route('account.orders.show', $this->order);
    }
}
