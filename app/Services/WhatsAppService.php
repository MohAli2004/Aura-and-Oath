<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    public function isConfigured(): bool
    {
        return filled(config('aura.payments.whatsapp.access_token'))
            && filled(config('aura.payments.whatsapp.phone_number_id'))
            && filled(config('aura.payments.whatsapp.admin_phone'));
    }

    public function sendText(string $toPhone, string $message): bool
    {
        if (! filled(config('aura.payments.whatsapp.access_token'))
            || ! filled(config('aura.payments.whatsapp.phone_number_id'))) {
            Log::info('WhatsApp skipped (not configured)', compact('toPhone', 'message'));

            return false;
        }

        $phoneNumberId = config('aura.payments.whatsapp.phone_number_id');
        $token = config('aura.payments.whatsapp.access_token');
        $to = preg_replace('/\D+/', '', $toPhone) ?: $toPhone;

        $url = "https://graph.facebook.com/v19.0/{$phoneNumberId}/messages";

        try {
            $response = Http::withToken((string) $token)
                ->acceptJson()
                ->asJson()
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    'to' => $to,
                    'type' => 'text',
                    'text' => [
                        'preview_url' => false,
                        'body' => $message,
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('WhatsApp send failed', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('WhatsApp send error', ['message' => $e->getMessage()]);

            return false;
        }
    }

    public function notifyAdmin(string $message): bool
    {
        $adminPhone = (string) config('aura.payments.whatsapp.admin_phone');

        if ($adminPhone === '') {
            Log::info('WhatsApp admin notify skipped (no admin phone)', compact('message'));

            return false;
        }

        return $this->sendText($adminPhone, $message);
    }
}
