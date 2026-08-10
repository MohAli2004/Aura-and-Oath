<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ContactMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $senderName,
        public string $senderEmail,
        public string $contactMessage
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $preview = str($this->contactMessage)->limit(140)->toString();

        return [
            'title' => 'New contact message',
            'message' => $this->senderName.' ('.$this->senderEmail.'): '.$preview,
            'url' => route('admin.notifications.index'),
            'sender_name' => $this->senderName,
            'sender_email' => $this->senderEmail,
            'contact_message' => $this->contactMessage,
        ];
    }
}
