@extends('layouts.storefront')
@section('title', __('storefront.page_title_checkout', ['name' => config('aura.name')]))
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
        data_get($draft, 'payment_method', ($paymentMethods[0] ?? null)?->value ?? '')
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
        addresses: @js(($addresses ?? collect())->map(fn ($address) => [
            'id' => (string) $address->id,
            'label' => $address->label ?: ($address->is_default ? __('storefront.default') : __('storefront.saved_address')),
            'full_name' => $address->full_name,
            'phone' => $address->phone,
            'line1' => $address->line1,
            'line2' => $address->line2,
            'city' => $address->city,
            'governorate' => $address->governorate,
        ])->values()),
        i18n: @js([
            'thisField' => __('storefront.this_field'),
            'fieldRequiredOrder' => __('storefront.field_required_order'),
            'fieldRequired' => __('storefront.field_required'),
        ]),
    })"
>
    <h1 class="font-display text-5xl mb-8">{{ __('storefront.checkout') }}</h1>
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
                {{ __('storefront.ordering_as') }} <span class="text-[var(--ao-charcoal)]">{{ auth()->user()->name }}</span>
                · {{ auth()->user()->email }}
            </div>

            <div x-show="formError" x-cloak class="alert alert-error" x-text="formError"></div>

            <div x-show="addresses.length" x-cloak>
                <h2 class="font-display text-2xl mb-3">{{ __('storefront.saved_addresses') }}</h2>
                <div class="space-y-2">
                    <template x-for="address in addresses" :key="address.id">
                        <button
                            type="button"
                            class="w-full text-start border border-beige bg-[#FFFCFA] p-4 transition hover:border-gold"
                            @click="applyAddress(address)"
                        >
                            <div class="text-xs uppercase tracking-[0.14em] text-taupe" x-text="address.label"></div>
                            <div class="mt-1 font-medium" x-text="address.full_name"></div>
                            <div class="text-sm text-taupe" x-text="[address.line1, address.city].filter(Boolean).join(', ')"></div>
                        </button>
                    </template>
                </div>
            </div>

            <div>
                <h2 class="font-display text-2xl mb-3">{{ __('storefront.shipping_address') }}</h2>
                <div class="grid sm:grid-cols-2 gap-4">
                    <div data-field-wrap>
                        <x-input
                            :label="__('storefront.full_name')"
                            name="shipping[full_name]"
                            value="{{ $shipping['full_name'] }}"
                            required
                            data-required
                            :data-required-label="__('storefront.full_name')"
                            @input="clearFieldError($event.target)"
                        />
                    </div>
                    <div data-field-wrap>
                        <x-input
                            :label="__('storefront.phone')"
                            name="shipping[phone]"
                            value="{{ $shipping['phone'] }}"
                            required
                            data-required
                            :data-required-label="__('storefront.phone')"
                            @input="clearFieldError($event.target)"
                        />
                    </div>
                    <div class="sm:col-span-2" data-field-wrap>
                        <x-input
                            :label="__('storefront.address_line_1')"
                            name="shipping[line1]"
                            value="{{ $shipping['line1'] }}"
                            required
                            data-required
                            :data-required-label="__('storefront.address_line_1')"
                            @input="clearFieldError($event.target)"
                        />
                    </div>
                    <div class="sm:col-span-2"><x-input :label="__('storefront.address_line_2')" name="shipping[line2]" value="{{ $shipping['line2'] }}" /></div>
                    <div data-field-wrap>
                        <x-input
                            :label="__('storefront.city')"
                            name="shipping[city]"
                            value="{{ $shipping['city'] }}"
                            required
                            data-required
                            :data-required-label="__('storefront.city')"
                            @input="clearFieldError($event.target)"
                        />
                    </div>
                    <x-input :label="__('storefront.district')" name="shipping[governorate]" value="{{ $shipping['governorate'] }}" />
                </div>
            </div>

            <div data-field-wrap>
                <label class="label" for="payment_method">{{ __('storefront.payment_method') }} <span class="normal-case tracking-wide text-[10px] font-normal text-blush">{{ __('storefront.required') }}</span></label>
                @if (count($paymentMethods))
                    <select
                        id="payment_method"
                        name="payment_method"
                        class="input"
                        x-model="method"
                        required
                        data-required
                        data-required-label="{{ __('storefront.payment_method') }}"
                        @change="clearFieldError($event.target)"
                    >
                        @foreach($paymentMethods as $method)
                        <option value="{{ $method->value }}" @selected($defaultPaymentMethod === $method->value)>{{ $method->label() }}</option>
                    @endforeach
                    </select>
                    @if(collect($paymentMethods)->contains(fn ($method) => $method === \App\Enums\PaymentMethod::WishAccount))
                    <div
                        class="mt-3 border border-beige bg-[#FFFCFA] p-4 text-sm space-y-2"
                        x-show="method === '{{ \App\Enums\PaymentMethod::WishAccount->value }}'"
                        x-cloak
                    >
                        <p class="font-medium">{{ __('storefront.pay_with_wish') }}</p>
                        @if ($whishPayEnabled)
                            <p class="text-taupe">{{ __('storefront.pay_wish_whish_app') }}</p>
                            <div><span class="text-taupe">{{ __('storefront.amount') }}:</span> <span x-text="format(total)">{{ money($quote['total']) }}</span></div>
                        @else
                            <p class="text-taupe">{{ store_wish('instructions') }}</p>
                            <div class="space-y-1">
                                <div><span class="text-taupe">{{ __('storefront.account_name') }}:</span> {{ store_wish('account_name') }}</div>
                                <div><span class="text-taupe">{{ __('storefront.wish_number') }}:</span> {{ store_wish('account_number') }}</div>
                                <div><span class="text-taupe">{{ __('storefront.amount') }}:</span> <span x-text="format(total)">{{ money($quote['total']) }}</span></div>
                            </div>
                        @endif
                    </div>
                    @endif
                @else
                    <p class="text-sm text-taupe">{{ __('storefront.payments_unavailable') }}</p>
                @endif
            </div>

            <div>
                <label class="label">{{ __('storefront.order_note') }}</label>
                <textarea name="customer_note" class="input" rows="3">{{ $customerNote }}</textarea>
            </div>

            <div class="border border-beige p-5 bg-[#FFFCFA] space-y-3" data-field-wrap>
                <h2 class="font-display text-2xl">{{ __('storefront.order_agreement') }}</h2>
                <div class="text-sm text-taupe space-y-2">
                    <p>{{ __('storefront.agreement_intro') }}</p>
                    <ul class="list-disc ps-5 space-y-1">
                        <li>{{ __('storefront.agreement_cancel') }}</li>
                        <li>{{ __('storefront.agreement_returns') }}</li>
                        <li>{{ __('storefront.agreement_used') }}</li>
                    </ul>
                    <p>
                        {{ __('storefront.read_our') }}
                        <a href="{{ route('pages.show', 'terms-of-service') }}" class="underline decoration-beige underline-offset-2 hover:text-charcoal">{{ __('storefront.terms') }}</a>
                        {{ __('storefront.and') }}
                        <a href="{{ route('pages.show', 'privacy-policy') }}" class="underline decoration-beige underline-offset-2 hover:text-charcoal">{{ __('storefront.privacy_policy') }}</a>.
                    </p>
                </div>
                <label class="flex items-start gap-3 text-sm">
                    <input
                        type="checkbox"
                        name="terms_agreed"
                        value="1"
                        class="mt-1 h-4 w-4"
                        required
                        data-required
                        data-required-label="{{ __('storefront.order_agreement') }}"
                        @checked(old('terms_agreed'))
                        @change="clearFieldError($event.target)"
                    >
                    <span>{{ __('storefront.agree_terms') }}</span>
                </label>
                @error('terms_agreed')
                    <p class="text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>

            <div class="space-y-3 text-sm text-taupe leading-relaxed">
                <x-trust-reassurance />
                <p>{{ __('storefront.prices_include_delivery') }}</p>
                <p>{{ __('storefront.after_order_updates') }}</p>
            </div>

            <button class="btn btn-primary" type="submit">{{ __('storefront.place_order') }}</button>
        </form>

        <aside class="space-y-6 lg:sticky lg:top-6 self-start">
            <div>
                <h2 class="font-display text-2xl mb-3">{{ __('storefront.delivery_region') }}</h2>
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
                                    data-required-label="{{ __('storefront.delivery_region') }}"
                                    @change="clearFieldError($event.target)"
                                >
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                                        <span class="font-medium">{{ $region->name }}</span>
                                        <span class="text-sm text-taupe">{{ money($region->fee) }}</span>
                                    </div>
                                    @if ($region->description)
                                        <p class="mt-1 text-sm text-taupe leading-relaxed">{{ __('storefront.region_includes', ['description' => $region->description]) }}</p>
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
                <h2 class="font-display text-2xl mb-4">{{ __('storefront.summary') }}</h2>
                @foreach($cart->items as $item)
                    <div class="flex justify-between text-sm py-2 border-b border-beige/70">
                        <span>{{ $item->product->localized('name') }} × {{ $item->quantity }}</span>
                        <span>{{ money($item->lineTotal()) }}</span>
                    </div>
                @endforeach
                <div class="mt-4 space-y-1 text-sm">
                    <div class="flex justify-between"><span>{{ __('storefront.subtotal') }}</span><span>{{ money($quote['subtotal']) }}</span></div>
                    <div class="flex justify-between"><span>{{ __('storefront.discount') }}</span><span>− {{ money($quote['discount_amount']) }}</span></div>
                    <div class="flex justify-between"><span>{{ __('storefront.delivery') }}</span><span x-text="format(fee)">{{ money($quote['delivery_fee']) }}</span></div>
                    <div class="flex justify-between font-medium text-base pt-2"><span>{{ __('storefront.total') }}</span><span x-text="format(total)">{{ money($quote['total']) }}</span></div>
                </div>
            </div>

            <form id="checkout-coupon-form" method="POST" action="{{ route('checkout.coupon') }}" class="border border-beige p-5 bg-[#FFFCFA] space-y-3">
                @csrf
                <label class="label">{{ __('storefront.coupon') }}</label>
                <input class="input" name="coupon_code" placeholder="AURA10" value="{{ old('coupon_code', session('checkout_coupon')) }}">
                @error('coupon')
                    <p class="text-sm text-blush">{{ $message }}</p>
                @enderror
                @error('coupon_code')
                    <p class="text-sm text-blush">{{ $message }}</p>
                @enderror
                <button class="btn btn-secondary w-full" type="submit">{{ __('storefront.apply_coupon') }}</button>
            </form>
        </aside>
    </div>
</div>
<script nonce="{{ csp_nonce() }}">
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            window.location.reload();
        }
    });
</script>
@endsection
