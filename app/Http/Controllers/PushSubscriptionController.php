<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Services\WebPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PushSubscriptionController extends Controller
{
    public function __construct(protected WebPushService $webPush) {}

    public function status(): JsonResponse
    {
        $user = Auth::user();

        return response()->json([
            'enabled' => $this->webPush->isConfigured(),
            'publicKey' => $this->webPush->publicKey(),
            'subscribed' => $user
                ? PushSubscription::query()->where('user_id', $user->id)->exists()
                : false,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($this->webPush->isConfigured(), 503, 'Push notifications are not configured.');

        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'string', 'max:32'],
        ]);

        $subscription = PushSubscription::query()->updateOrCreate(
            ['endpoint' => $data['endpoint']],
            [
                'user_id' => Auth::id(),
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
                'content_encoding' => $data['contentEncoding'] ?? 'aesgcm',
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
            ]
        );

        return response()->json([
            'ok' => true,
            'subscribed' => true,
            'id' => $subscription->id,
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
        ]);

        PushSubscription::query()
            ->where('user_id', Auth::id())
            ->where('endpoint', $data['endpoint'])
            ->delete();

        return response()->json([
            'ok' => true,
            'subscribed' => false,
        ]);
    }
}
