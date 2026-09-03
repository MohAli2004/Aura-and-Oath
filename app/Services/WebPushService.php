<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

class WebPushService
{
    public function isConfigured(): bool
    {
        return filled(config('webpush.vapid.public_key'))
            && filled(config('webpush.vapid.private_key'));
    }

    public function publicKey(): ?string
    {
        $key = config('webpush.vapid.public_key');

        return filled($key) ? (string) $key : null;
    }

    /**
     * @param  array{title?:string,message?:string,body?:string,url?:string|null,tag?:string|null}  $payload
     */
    public function sendToUser(User $user, array $payload): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        try {
            $subscriptions = PushSubscription::query()
                ->where('user_id', $user->id)
                ->get();

            if ($subscriptions->isEmpty()) {
                return;
            }

            $webPush = $this->client();
            $json = json_encode([
                'title' => (string) ($payload['title'] ?? config('aura.name', 'Aura & Oath')),
                'body' => (string) ($payload['body'] ?? $payload['message'] ?? ''),
                'url' => safe_url($payload['url'] ?? null, url('/')),
                'tag' => $payload['tag'] ?? 'aura-notification',
            ], JSON_THROW_ON_ERROR);

            $queued = 0;

            foreach ($subscriptions as $subscription) {
                // Rows stored before endpoint validation existed could still
                // point anywhere; never let one turn into an outbound request.
                if (! $this->isAllowedEndpoint($subscription->endpoint)) {
                    Log::warning('webpush.endpoint_rejected', ['subscription_id' => $subscription->id]);

                    continue;
                }

                try {
                    $webPush->queueNotification(
                        Subscription::create([
                            'endpoint' => $subscription->endpoint,
                            'publicKey' => $subscription->public_key,
                            'authToken' => $subscription->auth_token,
                            'contentEncoding' => $subscription->content_encoding ?: 'aesgcm',
                        ]),
                        $json
                    );
                    $queued++;
                } catch (Throwable $e) {
                    Log::warning('webpush.queue_failed', [
                        'subscription_id' => $subscription->id,
                        'message' => $e->getMessage(),
                    ]);
                }
            }

            if ($queued < 1) {
                return;
            }

            foreach ($webPush->flush() as $report) {
                if ($report->isSuccess()) {
                    continue;
                }

                $endpoint = $report->getRequest()?->getUri()?->__toString();
                $code = $report->getResponse()?->getStatusCode();

                if (in_array($code, [404, 410], true) && $endpoint) {
                    PushSubscription::query()->where('endpoint', $endpoint)->delete();
                }

                Log::info('webpush.delivery_failed', [
                    'endpoint' => $endpoint,
                    'reason' => $report->getReason(),
                    'status' => $code,
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('webpush.send_failed', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    protected function isAllowedEndpoint(?string $endpoint): bool
    {
        $parts = parse_url((string) $endpoint);

        if ($parts === false || empty($parts['host']) || strtolower($parts['scheme'] ?? '') !== 'https') {
            return false;
        }

        $host = strtolower($parts['host']);

        foreach ((array) config('security.push_endpoint_hosts', []) as $pattern) {
            if (Str::is(strtolower((string) $pattern), $host)) {
                return true;
            }
        }

        return false;
    }

    protected function client(): WebPush
    {
        return new WebPush([
            'VAPID' => [
                'subject' => (string) config('webpush.vapid.subject'),
                'publicKey' => (string) config('webpush.vapid.public_key'),
                'privateKey' => (string) config('webpush.vapid.private_key'),
            ],
        ]);
    }
}
