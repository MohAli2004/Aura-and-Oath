@extends('layouts.admin')
@section('heading', 'Control panel')
@section('title', 'Control panel')
@section('content')
<form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="max-w-3xl space-y-8">
    @csrf @method('PUT')

    <section class="border border-beige bg-[#FFFCFA] p-6 space-y-5">
        <div>
            <h2 class="font-display text-2xl">Customer contact</h2>
            <p class="text-sm text-taupe mt-1">Choose what shoppers can see. The phone number is hidden until you turn it on.</p>
        </div>

        <x-admin.toggle
            name="flags[show_phone_to_customers]"
            label="Show phone number to customers"
            hint="Footer, contact page, and other public pages."
            :checked="$flags['show_phone_to_customers']"
        />
        <x-input
            label="Phone number"
            name="fields[contact_phone]"
            value="{{ $fields['contact_phone'] }}"
            hint="Stored even while hidden so you can show it again later."
        />

        <x-admin.toggle
            name="flags[show_whatsapp_to_customers]"
            label="Show WhatsApp to customers"
            :checked="$flags['show_whatsapp_to_customers']"
        />
        <x-input label="WhatsApp number" name="fields[contact_whatsapp]" value="{{ $fields['contact_whatsapp'] }}" />

        <x-admin.toggle
            name="flags[show_email_to_customers]"
            label="Show email to customers"
            :checked="$flags['show_email_to_customers']"
        />
        <x-input label="Support email" name="fields[support_email]" type="email" value="{{ $fields['support_email'] }}" />

        <x-admin.toggle
            name="flags[show_address_to_customers]"
            label="Show address to customers"
            :checked="$flags['show_address_to_customers']"
        />
        <x-input label="Address" name="fields[contact_address]" value="{{ $fields['contact_address'] }}" />

        <x-admin.toggle
            name="flags[show_hours_to_customers]"
            label="Show support hours to customers"
            :checked="$flags['show_hours_to_customers']"
        />
        <x-input label="Support hours" name="fields[support_hours]" value="{{ $fields['support_hours'] }}" />
    </section>

    <section class="border border-beige bg-[#FFFCFA] p-6 space-y-5">
        <div>
            <h2 class="font-display text-2xl">Delivery choices</h2>
            <p class="text-sm text-taupe mt-1">Hide a region from checkout without deleting it. You can show it again here or on the Delivery page.</p>
        </div>

        @forelse($deliveryRegions as $region)
            <x-admin.toggle
                name="delivery_regions[{{ $region->id }}]"
                :label="$region->name.($region->code ? ' · '.$region->code : '').' · '.money($region->fee)"
                :hint="$region->is_active ? 'Visible at checkout.' : 'Hidden from customers.'"
                :checked="old('delivery_regions.'.$region->id, $region->is_active)"
            />
        @empty
            <p class="text-sm text-taupe">No delivery regions yet.</p>
        @endforelse

        <a href="{{ route('admin.delivery-regions.index') }}" class="inline-block text-sm underline">Add or edit regions</a>
    </section>

    <section class="border border-beige bg-[#FFFCFA] p-6 space-y-5">
        <div>
            <h2 class="font-display text-2xl">Payments</h2>
            <p class="text-sm text-taupe mt-1">Methods customers can choose at checkout. Keep at least one visible.</p>
        </div>
        <x-admin.toggle
            name="flags[payment_cod_enabled]"
            label="Cash on delivery"
            :checked="$flags['payment_cod_enabled']"
        />
        <x-admin.toggle
            name="flags[payment_wish_enabled]"
            label="Wish Account"
            :checked="$flags['payment_wish_enabled']"
        />
        <x-input label="Wish account name" name="fields[wish_account_name]" value="{{ $fields['wish_account_name'] }}" />
        <x-input label="Wish account number" name="fields[wish_account_number]" value="{{ $fields['wish_account_number'] }}" />
        <div>
            <label class="label" for="fields[wish_instructions]">Wish instructions</label>
            <textarea id="fields[wish_instructions]" name="fields[wish_instructions]" class="input" rows="3">{{ $fields['wish_instructions'] }}</textarea>
        </div>
    </section>

    <section class="border border-beige bg-[#FFFCFA] p-6 space-y-5">
        <div>
            <h2 class="font-display text-2xl">Homepage sections</h2>
            <p class="text-sm text-taupe mt-1">Show or hide blocks on the main storefront page.</p>
        </div>
        <x-admin.toggle name="flags[home_show_hot_offers]" label="Hot offers" :checked="$flags['home_show_hot_offers']" />
        <x-admin.toggle name="flags[home_show_featured]" label="Featured products" :checked="$flags['home_show_featured']" />
        <x-admin.toggle name="flags[home_show_shop_by_gender]" label="Shop by gender" :checked="$flags['home_show_shop_by_gender']" />
        <x-admin.toggle name="flags[home_show_women]" label="Women products" :checked="$flags['home_show_women']" />
        <x-admin.toggle name="flags[home_show_men]" label="Men products" :checked="$flags['home_show_men']" />
        <x-admin.toggle name="flags[home_show_unisex]" label="Unisex products" :checked="$flags['home_show_unisex']" />
        <x-admin.toggle name="flags[home_show_categories]" label="Categories" :checked="$flags['home_show_categories']" />
        <x-admin.toggle name="flags[home_show_bestsellers]" label="Bestsellers" :checked="$flags['home_show_bestsellers']" />
        <x-admin.toggle name="flags[home_show_new_arrivals]" label="New arrivals" :checked="$flags['home_show_new_arrivals']" />
        <x-admin.toggle name="flags[show_newsletter]" label="Footer newsletter" :checked="$flags['show_newsletter']" />
    </section>

    <section class="border border-beige bg-[#FFFCFA] p-6 space-y-5">
        <div>
            <h2 class="font-display text-2xl">Social links</h2>
            <p class="text-sm text-taupe mt-1">Empty URLs are not shown. Turn the group off to hide all of them.</p>
        </div>
        <x-admin.toggle name="flags[show_social_links]" label="Show social links" :checked="$flags['show_social_links']" />
        <x-input label="Instagram" name="fields[social_instagram]" value="{{ $fields['social_instagram'] }}" />
        <x-input label="Facebook" name="fields[social_facebook]" value="{{ $fields['social_facebook'] }}" />
        <x-input label="TikTok" name="fields[social_tiktok]" value="{{ $fields['social_tiktok'] }}" />
        <x-input label="YouTube" name="fields[social_youtube]" value="{{ $fields['social_youtube'] }}" />
    </section>

    <section class="border border-beige bg-[#FFFCFA] p-6 space-y-5">
        <div>
            <h2 class="font-display text-2xl">Store</h2>
        </div>
        <x-input label="Store name" name="fields[store_name]" value="{{ $fields['store_name'] }}" />
        <x-input label="Tax rate" name="fields[tax_rate]" value="{{ $fields['tax_rate'] }}" hint="Used as a decimal, e.g. 0.11 for 11%." />
        <div class="grid sm:grid-cols-2 gap-4">
            <x-input label="Currency" name="fields[currency]" value="{{ $fields['currency'] }}" />
            <x-input label="Currency symbol" name="fields[currency_symbol]" value="{{ $fields['currency_symbol'] }}" />
        </div>
        <div>
            <label class="label" for="fields[default_delivery_note]">Default delivery note</label>
            <textarea id="fields[default_delivery_note]" name="fields[default_delivery_note]" class="input" rows="3">{{ $fields['default_delivery_note'] }}</textarea>
        </div>
    </section>

    <section class="border border-beige bg-[#FFFCFA] p-6 space-y-4">
        <div>
            <h2 class="font-display text-2xl">Catalog &amp; marketing</h2>
            <p class="text-sm text-taupe mt-1">Add, hide, or edit items on their dedicated pages. Visibility flags on those records still apply.</p>
        </div>
        <div class="divide-y divide-beige border border-beige">
            @foreach($catalogLinks as $link)
                <a href="{{ route($link['route']) }}" class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-beige/30">
                    <div class="min-w-0">
                        <div class="text-sm">{{ $link['label'] }}</div>
                        <div class="text-xs text-taupe">{{ $link['status'] }} · {{ $link['hint'] }}</div>
                    </div>
                    <span class="text-xs text-taupe shrink-0">Manage</span>
                </a>
            @endforeach
        </div>
    </section>

    <section class="border border-beige bg-[#FFFCFA] p-6 space-y-4">
        <div>
            <h2 class="font-display text-2xl">Brand logo</h2>
            <p class="text-sm text-taupe mt-1">Shown in the storefront header, footer, login pages, and admin sidebar.</p>
        </div>

        <div>
            <span class="label" id="logo-label">Logo</span>
            <x-admin.image-upload
                name="logo"
                id="logo"
                frame="logo-wide"
                fit="contain"
                alt="Brand logo preview"
                empty="Click to upload"
                :src="$logoUrl"
                accept="image/png,image/jpeg,image/webp,image/svg+xml"
                aria-labelledby="logo-label"
            />
            <p class="text-xs text-taupe mt-1">PNG, JPG, WEBP, or SVG. Max 10MB. Transparent PNG works best.</p>
        </div>

        @if($logoUrl)
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="remove_logo" value="1">
                Remove current logo
            </label>
        @endif
    </section>

    <section class="border border-beige bg-[#FFFCFA] p-6 space-y-4">
        <div>
            <h2 class="font-display text-2xl">Favicon</h2>
            <p class="text-sm text-taupe mt-1">Browser tab icon for the storefront and admin.</p>
        </div>

        <div>
            <span class="label" id="favicon-label">Favicon</span>
            <x-admin.image-upload
                name="favicon"
                id="favicon"
                frame="favicon"
                fit="contain"
                alt="Favicon preview"
                empty="Click to upload"
                :src="$faviconUrl"
                accept="image/png,image/jpeg,image/webp,image/svg+xml,image/x-icon,.ico"
                aria-labelledby="favicon-label"
            />
            <p class="text-xs text-taupe mt-1">PNG or ICO recommended. Max 2MB.</p>
        </div>

        @if($faviconUrl)
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="remove_favicon" value="1">
                Remove current favicon
            </label>
        @endif
    </section>

    <section class="border border-beige bg-[#FFFCFA] p-6 space-y-4">
        <div>
            <h2 class="font-display text-2xl">Homepage background</h2>
            <p class="text-sm text-taupe mt-1">Hero background image on the main storefront page. Uploading a new image replaces the current one.</p>
        </div>

        <div>
            <span class="label" id="home-background-label">Background</span>
            <x-admin.image-upload
                name="home_background"
                id="home_background"
                frame="wide"
                fit="cover"
                alt="Homepage background preview"
                empty="Click to upload"
                :src="$homeBackgroundUrl"
                accept="image/png,image/jpeg,image/webp"
                aria-labelledby="home-background-label"
            />
            <p class="text-xs text-taupe mt-1">JPG, PNG, or WEBP. Max 10MB. Wide images work best.</p>
        </div>

        @if($homeBackgroundUrl)
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="remove_home_background" value="1">
                Remove current background
            </label>
        @endif
    </section>

    <section class="border border-beige bg-[#FFFCFA] p-6 space-y-6">
        <div>
            <h2 class="font-display text-2xl">Print documents</h2>
            <p class="text-sm text-taupe mt-1">Choose page size and which fields appear on invoices and packing slips.</p>
        </div>

        <div class="grid sm:grid-cols-2 gap-6">
            <div class="space-y-4">
                <h3 class="font-medium text-sm uppercase tracking-wider text-taupe">Invoice</h3>
                <div>
                    <label class="label" for="invoice_size">Page size</label>
                    <select id="invoice_size" name="invoice_size" class="input">
                        @foreach($printSizes as $size)
                            <option value="{{ $size }}" @selected(old('invoice_size', $invoiceSize) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                @php $selectedInvoice = old('invoice_fields', $invoiceFields); @endphp
                <div class="space-y-3">
                    <p class="text-xs uppercase tracking-wider text-taupe">Visible fields</p>
                    @foreach(config('aura.print.invoice') as $key => $label)
                        <label class="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                name="invoice_fields[]"
                                value="{{ $key }}"
                                @checked(in_array($key, $selectedInvoice, true))
                            >
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="space-y-4">
                <h3 class="font-medium text-sm uppercase tracking-wider text-taupe">Packing slip</h3>
                <div>
                    <label class="label" for="packing_slip_size">Page size</label>
                    <select id="packing_slip_size" name="packing_slip_size" class="input">
                        @foreach($printSizes as $size)
                            <option value="{{ $size }}" @selected(old('packing_slip_size', $packingSlipSize) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                @php $selectedPacking = old('packing_slip_fields', $packingSlipFields); @endphp
                <div class="space-y-3">
                    <p class="text-xs uppercase tracking-wider text-taupe">Visible fields</p>
                    @foreach(config('aura.print.packing_slip') as $key => $label)
                        <label class="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                name="packing_slip_fields[]"
                                value="{{ $key }}"
                                @checked(in_array($key, $selectedPacking, true))
                            >
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <button class="btn btn-primary" type="submit">Save control panel</button>
</form>
@endsection
