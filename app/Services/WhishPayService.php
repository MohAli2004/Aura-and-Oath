<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class WhishPayService
{
    public function isConfigured(): bool
    {
        if (! config('aura.payments.whish.enabled')) {
            return false;
        }

        return filled(config('aura.payments.whish.channel'))
            && filled(config('aura.payments.whish.secret'))
            && filled(config('aura.payments.whish.website_url'));
    }

    public function generateExternalId(): int
    {
        return (int) (now()->getTimestampMs() * 1000 + random_int(0, 999));
    }

    /**
     * Create a Whish payment for the order and persist external id + collect URL.
     *
     * @return string collectUrl
     */
    public function createPayment(Order $order): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Whish Pay is not configured.');
        }

        $externalId = $order->whish_external_id
            ? (int) $order->whish_external_id
            : $this->generateExternalId();

        $currency = strtoupper((string) ($order->currency ?: config('aura.currency', 'USD')));
        if (! in_array($currency, ['USD', 'LBP', 'AED'], true)) {
            $currency = 'USD';
        }

        $payload = [
            'amount' => (float) $order->total,
            'currency' => $currency,
            'invoice' => $order->order_number,
            'externalId' => $externalId,
            'successCallbackUrl' => route('payments.whish.callback.success'),
            'failureCallbackUrl' => route('payments.whish.callback.failure'),
            'successRedirectUrl' => route('payments.whish.return.success', ['order' => $order->id]),
            'failureRedirectUrl' => route('payments.whish.return.failure', ['order' => $order->id]),
        ];

        $response = $this->request('POST', '/payment/whish', $payload);

        if (! ($response['status'] ?? false)) {
            $message = $response['dialog']['message'] ?? ($response['code'] ?? 'Whish payment creation failed.');
            throw new RuntimeException((string) $message);
        }

        $collectUrl = $response['data']['collectUrl']
            ?? $response['data']['whishUrl']
            ?? null;

        if (! $collectUrl) {
            throw new RuntimeException('No payment URL returned from Whish API.');
        }

        $order->forceFill([
            'whish_external_id' => (string) $externalId,
            'whish_collect_url' => $collectUrl,
        ])->save();

        return $collectUrl;
    }

    /**
     * @return array{collectStatus?: string, amount?: float|int, currency?: string, transactionId?: string}
     */
    public function getPaymentStatus(string $currency, string|int $externalId): array
    {
        $response = $this->request('POST', '/payment/collect/status', [
            'currency' => strtoupper($currency),
            'externalId' => (int) $externalId,
        ]);

        if (! ($response['status'] ?? false)) {
            $message = $response['dialog']['message'] ?? 'Failed to get Whish payment status.';
            throw new RuntimeException((string) $message);
        }

        return $response['data'] ?? [];
    }

    public function validateAmount(float|int $received, float|int $expected, string $currency): bool
    {
        $tolerance = strtoupper($currency) === 'LBP' ? 100 : 0.02;
        $difference = abs(round(((float) $received) - ((float) $expected), 4));

        return $difference <= $tolerance;
    }

    /**
     * @return array<string, mixed>
     */
    protected function request(string $method, string $path, ?array $body = null): array
    {
        $base = $this->baseUrl();
        $url = rtrim($base, '/').$path;

        $headers = [
            'channel' => (string) config('aura.payments.whish.channel'),
            'secret' => (string) config('aura.payments.whish.secret'),
            'websiteurl' => (string) config('aura.payments.whish.website_url'),
            'Accept' => 'application/json',
        ];

        try {
            $pending = Http::timeout(30)->withHeaders($headers)->asJson();

            $response = strtoupper($method) === 'GET'
                ? $pending->get($url)
                : $pending->post($url, $body ?? []);
        } catch (ConnectionException $e) {
            Log::warning('Whish Pay network error', ['message' => $e->getMessage()]);
            throw new RuntimeException('Unable to reach Whish Pay. Please try again.');
        }

        if (! $response->successful()) {
            Log::warning('Whish Pay HTTP error', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw new RuntimeException('Unexpected response from Whish Pay.');
        }

        return $json;
    }

    protected function baseUrl(): string
    {
        $environment = config('aura.payments.whish.environment', 'sandbox');

        return $environment === 'production'
            ? 'https://whish.money/itel-service/api'
            : 'https://lb.sandbox.whish.money/itel-service/api';
    }
}
