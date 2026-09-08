<div class="border border-beige p-4 text-sm space-y-2 bg-ivory/50">
    <div class="font-medium">{{ __('storefront.return_when_accept') }}</div>
    <p>{{ __('storefront.return_only_real') }}</p>
    <ul class="list-disc ps-5 text-taupe space-y-1">
        <li>{{ __('storefront.return_damaged') }}</li>
        <li>{{ __('storefront.return_wrong_item') }}</li>
        <li>{{ __('storefront.return_missing') }}</li>
    </ul>
    <p class="text-taupe">{{ __('storefront.return_no_used') }}</p>
</div>

<div>
    <div class="label mb-2">{{ __('storefront.return_items_heading') }}</div>
    <div class="space-y-2">
        @foreach($order->returnableItems() as $item)
            @php
                $oldRow = old('items.'.$item->id);
                $selected = is_array($oldRow)
                    ? filled($oldRow['id'] ?? null)
                    : old('items') === null;
                $oldQty = (int) (is_array($oldRow) ? ($oldRow['quantity'] ?? $item->quantity) : $item->quantity);
            @endphp
            <div
                class="flex flex-col gap-3 border border-beige p-3 text-sm sm:flex-row sm:items-center"
                x-data="{ selected: @js($selected) }"
            >
                <label class="flex items-start gap-3 min-w-0 flex-1 cursor-pointer">
                    <input
                        type="checkbox"
                        name="items[{{ $item->id }}][id]"
                        value="{{ $item->id }}"
                        class="mt-1 h-4 w-4"
                        x-model="selected"
                    >
                    <span class="min-w-0">
                        <span class="block">{{ $item->product_name }}@if($item->variant_name) — {{ $item->variant_name }}@endif</span>
                        <span class="text-taupe">{{ __('storefront.ordered_qty', ['qty' => $item->quantity, 'total' => money($item->line_total)]) }}</span>
                    </span>
                </label>
                <div class="sm:w-36">
                    <label class="label" for="return-qty-{{ $item->id }}">{{ __('storefront.return_amount') }}</label>
                    <input
                        id="return-qty-{{ $item->id }}"
                        type="number"
                        name="items[{{ $item->id }}][quantity]"
                        min="1"
                        max="{{ $item->quantity }}"
                        value="{{ $oldQty }}"
                        class="input"
                        :disabled="!selected"
                        :required="selected"
                    >
                </div>
            </div>
        @endforeach
    </div>
    @error('items')
        <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
    @enderror
    @error('items.*')
        <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
    @enderror
    @error('items.*.quantity')
        <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
    @enderror
</div>

<div
    x-data="{
        preview: null,
        fileName: '',
        photoCaptured: @js(__('storefront.return_photo_captured')),
        photoHint: @js(__('storefront.return_photo_hint')),
        setFile(file, namedInput) {
            if (! file) return;
            const transfer = new DataTransfer();
            transfer.items.add(file);
            namedInput.files = transfer.files;
            this.fileName = file.name || this.photoCaptured;
            if (this.preview) URL.revokeObjectURL(this.preview);
            this.preview = URL.createObjectURL(file);
        },
    }"
>
    <div class="label">{{ __('storefront.return_photo') }} <span class="normal-case tracking-wide text-[10px] font-normal text-blush">{{ __('storefront.required') }}</span></div>
    <input
        id="photo"
        name="photo"
        type="file"
        accept="image/*"
        class="sr-only"
        required
        x-ref="photo"
        @change="setFile($event.target.files[0], $refs.photo)"
    >
    <input
        type="file"
        accept="image/*"
        capture="environment"
        class="sr-only"
        x-ref="camera"
        @change="setFile($event.target.files[0], $refs.photo)"
    >
    <div class="mt-2 flex flex-wrap gap-2">
        <button type="button" class="btn btn-secondary" @click="$refs.photo.click()">{{ __('storefront.return_choose_file') }}</button>
        <button type="button" class="btn btn-secondary" @click="$refs.camera.click()">{{ __('storefront.return_take_picture') }}</button>
    </div>
    <p class="mt-1 text-xs text-taupe" x-text="fileName ? fileName : photoHint"></p>
    <img x-show="preview" x-cloak :src="preview" alt="{{ __('storefront.return_photo_alt') }}" class="mt-3 max-h-48 w-full object-contain border border-beige bg-[#FFFCFA]">
    @error('photo')
        <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
    @enderror
</div>

<div>
    <label class="label" for="reason">{{ __('storefront.return_details') }} <span class="normal-case tracking-wide text-[10px] font-normal text-blush">{{ __('storefront.required') }}</span></label>
    <textarea id="reason" name="reason" class="input" rows="4" required minlength="8" placeholder="{{ __('storefront.return_details_placeholder') }}">{{ old('reason') }}</textarea>
    @error('reason')
        <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
    @enderror
</div>

<label class="flex items-start gap-3 text-sm">
    <input type="checkbox" name="policy_accepted" value="1" class="mt-1 h-4 w-4" @checked(old('policy_accepted')) required>
    <span>{{ __('storefront.return_policy_confirm') }}</span>
</label>
@error('policy_accepted')
    <p class="text-sm text-red-700">{{ $message }}</p>
@enderror
