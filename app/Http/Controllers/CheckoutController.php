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
use Illuminate\Http\Response;
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

    public function create(Request $request): View|RedirectResponse|Response
    {
        $cart = $this->cartService->getOrCreateCart();
        $cart->load(['items.product.images', 'items.variant']);

        if ($cart->items->isEmpty()) {
            $completedOrderId = $request->session()->pull('last_completed_order_id');

            if ($completedOrderId) {
                return redirect()
                    ->route('account.orders.show', $completedOrderId)
                    ->with('info', 'Your order was already placed.');
            }

            return redirect()->route('cart.index')->with('error', 'Your bag is empty.');
        }

        $token = $request->session()->get('checkout_idempotency') ?? $this->checkoutService->newIdempotencyToken();
        $request->session()->put('checkout_idempotency', $token);

        $draft = $request->session()->get('checkout_draft', []);
        $regionId = old('delivery_region_id', data_get($draft, 'delivery_region_id'));

        $quote = $this->pricingService->quote(
            $cart,
            $request->session()->get('checkout_coupon'),
            $regionId ? (int) $regionId : null,
            Auth::user()
        );

        return response()
            ->view('storefront.checkout', [
                'cart' => $cart,
                'quote' => $quote,
                'regions' => $this->deliveryFeeService->activeRegions(),
                'paymentMethods' => PaymentMethod::cases(),
                'idempotencyToken' => $token,
                'addresses' => Auth::user()->addresses ?? collect(),
                'whishPayEnabled' => $this->whishPayService->isConfigured(),
                'draft' => $draft,
                'hasServerDraft' => $request->session()->hasOldInput() || filled($draft),
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    public function applyCoupon(Request $request): RedirectResponse
    {
        $request->validate(['coupon_code' => ['required', 'string']]);
        $request->session()->put('checkout_coupon', $request->string('coupon_code')->toString());
        $this->storeCheckoutDraft($request);

        return back()->with('success', 'Coupon applied.')->withInput($request->except(['_token', 'coupon_code']));
    }

    public function store(CheckoutRequest $request): RedirectResponse
    {
        $this->storeCheckoutDraft($request);

        $token = $request->input('idempotency_token') ?: $request->session()->get('checkout_idempotency');

        $user = Auth::user();

        $order = $this->checkoutService->placeOrder($user, [
            ...$request->validated(),
            'coupon_code' => $request->session()->get('checkout_coupon'),
            'idempotency_token' => $token,
            'shipping' => $request->input('shipping'),
            'billing' => $request->input('billing'),
        ]);

        $request->session()->forget(['checkout_idempotency', 'checkout_coupon', 'checkout_draft']);
        $request->session()->put('last_completed_order_id', $order->id);

        if ($order->payment_method === PaymentMethod::WishAccount && $this->whishPayService->isConfigured()) {
            try {
                $collectUrl = $this->whishPayService->createPayment($order);

                return redirect()->away($collectUrl);
            } catch (RuntimeException $e) {
                return redirect()
                    ->route('account.orders.show', $order)
                    ->with('error', 'Order placed, but Wish Pay could not start: '.$e->getMessage())
                    ->with('order_just_placed', true);
            }
        }

        return redirect()
            ->route('account.orders.show', $order)
            ->with('success', 'Order placed successfully. Awaiting approval.')
            ->with('order_just_placed', true);
    }

    /**
     * @return array<string, mixed>
     */
    protected function storeCheckoutDraft(Request $request): array
    {
        $existing = $request->session()->get('checkout_draft', []);

        $draft = [
            'shipping' => [
                'full_name' => $request->input('shipping.full_name', data_get($existing, 'shipping.full_name')),
                'phone' => $request->input('shipping.phone', data_get($existing, 'shipping.phone')),
                'line1' => $request->input('shipping.line1', data_get($existing, 'shipping.line1')),
                'line2' => $request->input('shipping.line2', data_get($existing, 'shipping.line2')),
                'city' => $request->input('shipping.city', data_get($existing, 'shipping.city')),
                'governorate' => $request->input('shipping.governorate', data_get($existing, 'shipping.governorate')),
            ],
            'payment_method' => $request->input('payment_method', data_get($existing, 'payment_method')),
            'delivery_region_id' => $request->input('delivery_region_id', data_get($existing, 'delivery_region_id')),
            'customer_note' => $request->input('customer_note', data_get($existing, 'customer_note')),
        ];

        $request->session()->put('checkout_draft', $draft);

        return $draft;
    }
}
