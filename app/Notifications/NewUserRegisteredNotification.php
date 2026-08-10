<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewUserRegisteredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public User $user,
        public string $method = 'email'
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
            ->subject('New customer registered')
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->body())
            ->action('View customer', url('/admin/customers/'.$this->user->id));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New customer registered',
            'message' => $this->body(),
            'url' => route('admin.customers.show', $this->user),
            'user_id' => $this->user->id,
            'email' => $this->user->email,
            'name' => $this->user->name,
            'method' => $this->method,
            'for_admin' => true,
        ];
    }

    protected function body(): string
    {
        $via = $this->method === 'google' ? 'Google' : 'email';

        return $this->user->name.' ('.$this->user->email.') registered via '.$via.'.';
    }
}
