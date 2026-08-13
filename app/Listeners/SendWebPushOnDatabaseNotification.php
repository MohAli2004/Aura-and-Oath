<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\WebPushService;
use App\Support\NotificationPresenter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendWebPushOnDatabaseNotification implements ShouldQueue
{
    public function __construct(protected WebPushService $webPush) {}

    public function handle(NotificationSent $event): void
    {
        if ($event->channel !== 'database') {
            return;
        }

        if (! $this->webPush->isConfigured()) {
            return;
        }

        $notifiable = $event->notifiable;
        if (! $notifiable instanceof User) {
            return;
        }

        try {
            $databaseNotification = $event->response;

            if (! $databaseNotification instanceof DatabaseNotification) {
                if (method_exists($event->notification, 'toArray')) {
                    $data = $event->notification->toArray($notifiable);
                    $this->webPush->sendToUser($notifiable, [
                        'title' => (string) ($data['title'] ?? 'Notification'),
                        'body' => (string) ($data['message'] ?? ''),
                        'url' => $data['url'] ?? url('/'),
                        'tag' => 'aura-notification',
                    ]);
                }

                return;
            }

            $presented = NotificationPresenter::present($databaseNotification);

            $this->webPush->sendToUser($notifiable, [
                'title' => $presented['title'],
                'body' => $presented['message'],
                'url' => $presented['url'] ?: url('/'),
                'tag' => 'notification-'.$databaseNotification->id,
            ]);
        } catch (Throwable $e) {
            Log::warning('webpush.listener_failed', [
                'user_id' => $notifiable->id ?? null,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
