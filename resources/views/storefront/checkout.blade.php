@extends('layouts.storefront')
@section('title', 'Checkout — '.config('aura.name'))
@section('content')
@php
    $draft = $draft ?? [];
    $shipping = [
        'full_name' => old('shipping.full_name', data_get($draft, 'shipping.full_name', auth()->user()->name)),
        'phone' => old('shipping.phone', data_get($draft, 'shipping.phone', auth()->user()->phone)),
        'line1' => old('shipping.line1', data_get($draft, 'shipping.line1')),
        'line2' => old('shipping.line2', data_get($draft, 'shipping.line2')),
        'city' => old('shipping.city', data_get($draft, 'shipping.city', 'Beirut')),
        'governorate' => old('shipping.governorate', data_get($draft, 'shipping.governorate')),
    ];
    $defaultRegionId = (string) old(
        'delivery_region_id',
        data_get($draft, 'delivery_region_id', $regions->first()?->id)
    );
    $defaultPaymentMethod = old(
        'payment_method',
        data_get($draft, 'payment_method', $paymentMethods[0]->value)
    );
    $customerNote = old('customer_note', data_get($draft, 'customer_note'));
    $regionFees = $regions->mapWithKeys(fn ($region) => [(string) $region->id => (float) $region->fee]);
@endphp
<div
    class="max-w-6xl mx-auto px-4 sm:px-6 py-10"
    x-data="checkoutPage({
        regionId: @js($defaultRegionId),
        fees: @js($regionFees),
        subtotal: @js((float) $quote['subtotal']),
        discount: @js((float) $quote['discount_amount']),
        method: @js($defaultPaymentMethod),
        currency: @js(config('aura.currency', 'USD')),
        hasServerDraft: @js((bool) ($hasServerDraft ?? false)),
    })"
>
    <h1 class="font-display text-5xl mb-8">Checkout</h1>
    <div class="grid lg:grid-cols-[1.2fr_0.8fr] gap-10">
        <form
            id="checkout-form"
            method="POST"
            action="{{ route('checkout.store') }}"
            class="space-y-6"
            novalidate
            @submit="validateBeforeSubmit($event)"
        >
            @csrf
            <input type="hidden" name="idempotency_token" value="{{ $idempotencyToken }}">
            <div class="text-sm text-taupe">
                Ordering as <span class="text-[var(--ao-charcoal)]">{{ auth()->user()->name }}</span>
                · {{ auth()->user()->email }}
            </div>

            <div x-show="formError" x-cloak class="alert alert-error" x-text="formError"></div>

            <div>
                <h2 class="font-display text-2xl mb-3">Shipping address</h2>
                <div class="grid sm:grid-cols-2 gap-4">
                    <div data-field-wrap>
                        <x-input
                            label="Full name"
                            name="shipping[full_name]"
                            value="{{ $shipping['full_name'] }}"
                            required
                            data-required
                            data-required-label="Full name"
                            @input="clearFieldError($event.target)"
                        />
                    </div>
                    <div data-field-wrap>
                        <x-input
                            label="Phone"
                            name="shipping[phone]"
                            value="{{ $shipping['phone'] }}"
                            required
                            data-required
                            data-required-label="Phone"
                            @input="clearFieldError($event.target)"
                        />
                    </div>
                    <div class="sm:col-span-2" data-field-wrap>
                        <x-input
                            label="Address line 1"
                            name="shipping[line1]"
                            value="{{ $shipping['line1'] }}"
                            required
                            data-required
                            data-required-label="Address line 1"
                            @input="clearFieldError($event.target)"
                        />
                    </div>
                    <div class="sm:col-span-2"><x-input label="Address line 2" name="shipping[line2]" value="{{ $shipping['line2'] }}" /></div>
                    <div data-field-wrap>
                        <x-input
                            label="City"
                            name="shipping[city]"
                            value="{{ $shipping['city'] }}"
                            required
                            data-required
                            data-required-label="City"
                            @input="clearFieldError($event.target)"
                        />
                    </div>
                    <x-input label="District" name="shipping[governorate]" value="{{ $shipping['governorate'] }}" />
                </div>
            </div>

            <div data-field-wrap>
                <label class="label" for="payment_method">Payment method <span class="normal-case tracking-wide text-[10px] font-normal text-blush">Required</span></label>
                <select
                    id="payment_method"
                    name="payment_method"
                    class="input"
                    x-model="method"
                    required
                    data-required
                    data-required-label="Payment method"
                    @change="clearFieldError($event.target)"
                >
                    @foreach($paymentMethods as $method)
                        <option value="{{ $method->value }}" @selected($defaultPaymentMethod === $method->value)>{{ $method->label() }}</option>
                    @endforeach
                </select>
                <div
                    class="mt-3 border border-beige bg-[#FFFCFA] p-4 text-sm space-y-2"
                    x-show="method === '{{ \App\Enums\PaymentMethod::WishAccount->value }}'"
                    x-cloak
                >
                    <p class="font-medium">Pay with Wish Account</p>
                    @if ($whishPayEnabled)
                        <p class="text-taupe">After you place the order, you will confirm payment in the Whish app (phone + OTP). The order total will be charged from your Wish balance.</p>
                        <div><span class="text-taupe">Amount:</span> <span x-text="format(total)">{{ money($quote['total']) }}</span></div>
                    @else
                        <p class="text-taupe">{{ config('aura.payments.wish.instructions') }}</p>
                        <div class="space-y-1">
                            <div><span class="text-taupe">Account name:</span> {{ config('aura.payments.wish.account_name') }}</div>
                            <div><span class="text-taupe">Wish number:</span> {{ config('aura.payments.wish.account_number') }}</div>
                            <div><span class="text-taupe">Amount:</span> <span x-text="format(total)">{{ money($quote['total']) }}</span></div>
                        </div>
                    @endif
                </div>
            </div>

            <div>
                <label class="label">Order note</label>
                <textarea name="customer_note" class="input" rows="3">{{ $customerNote }}</textarea>
            </div>

            <button class="btn btn-primary" type="submit">Place order</button>
        </form>

        <aside class="space-y-6 lg:sticky lg:top-6 self-start">
            <div>
                <h2 class="font-display text-2xl mb-3">Delivery region</h2>
                <div class="space-y-3" data-field-wrap>
                    @foreach($regions as $region)
                        <label class="block border border-beige p-4 bg-[#FFFCFA] cursor-pointer transition"
                               :class="regionId == '{{ $region->id }}' ? 'border-[var(--ao-gold)]' : ''">
                            <div class="flex items-start gap-3">
                                <input
                                    type="radio"
                                    class="mt-1"
                                    form="checkout-form"
                                    name="delivery_region_id"
                                    value="{{ $region->id }}"
                                    x-model="regionId"
                                    @checked($defaultRegionId == (string) $region->id)
                                    required
                                    data-required
                                    data-required-label="Delivery region"
                                    @change="clearFieldError($event.target)"
                                >
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                                        <span class="font-medium">{{ $region->name }}</span>
                                        <span class="text-sm text-taupe">{{ money($region->fee) }}</span>
                                    </div>
                                    @if ($region->description)
                                        <p class="mt-1 text-sm text-taupe leading-relaxed">Includes: {{ $region->description }}</p>
                                    @endif
                                </div>
                            </div>
                        </label>
                    @endforeach
                </div>
                @error('delivery_region_id')
                    <p class="mt-2 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>

            <div class="border border-beige p-5 bg-[#FFFCFA]">
                <h2 class="font-display text-2xl mb-4">Summary</h2>
                @foreach($cart->items as $item)
                    <div class="flex justify-between text-sm py-2 border-b border-beige/70">
                        <span>{{ $item->product->name }} × {{ $item->quantity }}</span>
                        <span>{{ money($item->lineTotal()) }}</span>
                    </div>
                @endforeach
                <div class="mt-4 space-y-1 text-sm">
                    <div class="flex justify-between"><span>Subtotal</span><span>{{ money($quote['subtotal']) }}</span></div>
                    <div class="flex justify-between"><span>Discount</span><span>− {{ money($quote['discount_amount']) }}</span></div>
                    <div class="flex justify-between"><span>Delivery</span><span x-text="format(fee)">{{ money($quote['delivery_fee']) }}</span></div>
                    <div class="flex justify-between font-medium text-base pt-2"><span>Total</span><span x-text="format(total)">{{ money($quote['total']) }}</span></div>
                </div>
            </div>

            <form id="checkout-coupon-form" method="POST" action="{{ route('checkout.coupon') }}" class="border border-beige p-5 bg-[#FFFCFA] space-y-3">
                @csrf
                <label class="label">Coupon</label>
                <input class="input" name="coupon_code" placeholder="AURA10" value="{{ old('coupon_code', session('checkout_coupon')) }}">
                <button class="btn btn-secondary w-full" type="submit">Apply coupon</button>
            </form>
        </aside>
    </div>
</div>
<script>
    // After placing an order, browser Back can restore checkout from cache.
    // Force a fresh load so empty-cart / completed-order redirects run.
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            window.location.reload();
        }
    });
</script>
@endsection
