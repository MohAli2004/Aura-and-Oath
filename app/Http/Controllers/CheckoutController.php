<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Http\Requests\CheckoutRequest;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\DeliveryFeeService;
use App\Services\OrderPricingService;
use App\Services\WhishPayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use RuntimeException;

class CheckoutController extends Controller
{
    public function __construct(
        protected CartService $cartService,
        protected CheckoutService $checkoutService,
        protected OrderPricingService $pricingService,
        protected DeliveryFeeService $deliveryFeeService,
        protected WhishPayService $whishPayService
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        $cart = $this->cartService->getOrCreateCart();
        $cart->load(['items.product.images', 'items.variant']);

        if ($cart->items->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Your bag is empty.');
        }

        $token = $request->session()->get('checkout_idempotency') ?? $this->checkoutService->newIdempotencyToken();
        $request->session()->put('checkout_idempotency', $token);

        $quote = $this->pricingService->quote(
            $cart,
            $request->session()->get('checkout_coupon'),
            $request->integer('delivery_region_id') ?: null,
            Auth::user()
        );

        return view('storefront.checkout', [
            'cart' => $cart,
            'quote' => $quote,
            'regions' => $this->deliveryFeeService->activeRegions(),
            'paymentMethods' => PaymentMethod::cases(),
            'idempotencyToken' => $token,
            'addresses' => Auth::user()->addresses ?? collect(),
            'whishPayEnabled' => $this->whishPayService->isConfigured(),
        ]);
    }

    public function applyCoupon(Request $request): RedirectResponse
    {
        $request->validate(['coupon_code' => ['required', 'string']]);
        $request->session()->put('checkout_coupon', $request->string('coupon_code')->toString());

        return back()->with('success', 'Coupon applied.');
    }

    public function store(CheckoutRequest $request): RedirectResponse
    {
        $token = $request->input('idempotency_token') ?: $request->session()->get('checkout_idempotency');

        $user = Auth::user();

        $order = $this->checkoutService->placeOrder($user, [
            ...$request->validated(),
            'coupon_code' => $request->session()->get('checkout_coupon'),
            'idempotency_token' => $token,
            'shipping' => $request->input('shipping'),
            'billing' => $request->input('billing'),
        ]);

        $request->session()->forget(['checkout_idempotency', 'checkout_coupon']);

        if ($order->payment_method === PaymentMethod::WishAccount && $this->whishPayService->isConfigured()) {
            try {
                $collectUrl = $this->whishPayService->createPayment($order);

                return redirect()->away($collectUrl);
            } catch (RuntimeException $e) {
                return redirect()
                    ->route('account.orders.show', $order)
                    ->with('error', 'Order placed, but Wish Pay could not start: '.$e->getMessage());
            }
        }

        return redirect()->route('account.orders.show', $order)->with('success', 'Order placed successfully. Awaiting approval.');
    }
}
