<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\NotificationService;
use App\Services\OrderService;
use App\Services\WhishPayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class WhishPaymentController extends Controller
{
    public function __construct(
        protected WhishPayService $whish,
        protected OrderService $orders,
        protected NotificationService $notifications
    ) {}

    public function callbackSuccess(Request $request): Response
    {
        $this->handleCallback($request, expectSuccess: true);

        return response('OK', 200);
    }

    public function callbackFailure(Request $request): Response
    {
        $this->handleCallback($request, expectSuccess: false);

        return response('OK', 200);
    }

    public function returnSuccess(Request $request, Order $order): RedirectResponse
    {
        abort_unless(Auth::check() && (int) $order->user_id === (int) Auth::id(), 403);

        // Re-verify in case callback has not arrived yet.
        $this->confirmPaidIfSuccessful($order);

        return redirect()
            ->route('account.orders.show', $order)
            ->with('success', 'Payment received. Thank you.');
    }

    public function returnFailure(Order $order): RedirectResponse
    {
        abort_unless(Auth::check() && (int) $order->user_id === (int) Auth::id(), 403);

        return redirect()
            ->route('account.orders.show', $order)
            ->with('error', 'Wish payment was not completed. You can try again from this order page.');
    }

    public function continue(Order $order): RedirectResponse
    {
        abort_unless(Auth::check() && (int) $order->user_id === (int) Auth::id(), 403);

        if ($order->payment_method !== PaymentMethod::WishAccount) {
            return redirect()->route('account.orders.show', $order);
        }

        if ($order->payment_status === PaymentStatus::Paid) {
            return redirect()->route('account.orders.show', $order)->with('success', 'This order is already paid.');
        }

        if (! $this->whish->isConfigured()) {
            return redirect()->route('account.orders.show', $order)
                ->with('error', 'Online Wish Pay is not enabled. Please transfer manually using the details on this page.');
        }

        try {
            if ($order->whish_collect_url) {
                return redirect()->away($order->whish_collect_url);
            }

            $url = $this->whish->createPayment($order);

            return redirect()->away($url);
        } catch (RuntimeException $e) {
            return redirect()->route('account.orders.show', $order)->with('error', $e->getMessage());
        }
    }

    protected function handleCallback(Request $request, bool $expectSuccess): void
    {
        $externalId = $this->parseExternalId($request);
        $currency = strtoupper((string) ($request->query('currency') ?: $request->input('currency') ?: ''));

        if (! $externalId || $currency === '') {
            Log::warning('Whish callback missing params', [
                'query' => $request->query(),
                'input' => $request->all(),
            ]);

            return;
        }

        $order = Order::query()->where('whish_external_id', (string) $externalId)->first();

        if (! $order) {
            Log::warning('Whish callback unknown externalId', compact('externalId', 'currency'));

            return;
        }

        if (! $expectSuccess) {
            Log::info('Whish payment failure callback', [
                'order' => $order->order_number,
                'externalId' => $externalId,
            ]);

            return;
        }

        $this->confirmPaidIfSuccessful($order, $currency);
    }

    protected function confirmPaidIfSuccessful(Order $order, ?string $currency = null): void
    {
        if ($order->payment_status === PaymentStatus::Paid) {
            return;
        }

        if (! $order->whish_external_id || ! $this->whish->isConfigured()) {
            return;
        }

        $currency = strtoupper($currency ?: (string) ($order->currency ?: config('aura.currency', 'USD')));

        try {
            $status = $this->whish->getPaymentStatus($currency, $order->whish_external_id);
        } catch (RuntimeException $e) {
            Log::warning('Whish status check failed', [
                'order' => $order->order_number,
                'message' => $e->getMessage(),
            ]);

            return;
        }

        if (($status['collectStatus'] ?? null) !== 'success') {
            return;
        }

        if (isset($status['amount']) && ! $this->whish->validateAmount($status['amount'], (float) $order->total, $currency)) {
            Log::warning('Whish amount mismatch', [
                'order' => $order->order_number,
                'expected' => $order->total,
                'received' => $status['amount'] ?? null,
            ]);

            return;
        }

        if (! empty($status['transactionId'])) {
            $order->forceFill(['whish_transaction_id' => (string) $status['transactionId']])->save();
        }

        $before = $order->payment_status;
        $order = $this->orders->markPaid($order, null, 'Wish payment confirmed via Whish Pay.');

        if ($before !== PaymentStatus::Paid && $order->payment_status === PaymentStatus::Paid) {
            $this->notifications->notifyAdminWishPayment($order);
        }
    }

    protected function parseExternalId(Request $request): ?int
    {
        $raw = $request->query('externalId') ?? $request->input('externalId');

        if ($raw === null || $raw === '') {
            return null;
        }

        if (! preg_match('/^\d+$/', (string) $raw)) {
            return null;
        }

        $value = (int) $raw;

        return $value > 0 ? $value : null;
    }
}
