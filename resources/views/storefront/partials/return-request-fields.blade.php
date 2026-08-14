<div class="border border-beige p-4 text-sm space-y-2 bg-ivory/50">
    <div class="font-medium">When we accept a return</div>
    <p>We only return an item when there is a real problem, for example:</p>
    <ul class="list-disc ps-5 text-taupe space-y-1">
        <li>The item arrived damaged or defective</li>
        <li>You received a different item than the one you ordered</li>
        <li>Something is missing from the order</li>
    </ul>
    <p class="text-taupe">We do not accept returns for items that were used, opened without a defect, or broken after delivery.</p>
</div>

<div>
    <div class="label mb-2">Items to return</div>
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
                        <span class="text-taupe">Ordered {{ $item->quantity }} · {{ money($item->line_total) }}</span>
                    </span>
                </label>
                <div class="sm:w-36">
                    <label class="label" for="return-qty-{{ $item->id }}">Amount to return</label>
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
        setFile(file, namedInput) {
            if (! file) return;
            const transfer = new DataTransfer();
            transfer.items.add(file);
            namedInput.files = transfer.files;
            this.fileName = file.name || 'Photo captured';
            if (this.preview) URL.revokeObjectURL(this.preview);
            this.preview = URL.createObjectURL(file);
        },
    }"
>
    <div class="label">Photo of the item <span class="normal-case tracking-wide text-[10px] font-normal text-blush">Required</span></div>
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
        <button type="button" class="btn btn-secondary" @click="$refs.photo.click()">Choose file</button>
        <button type="button" class="btn btn-secondary" @click="$refs.camera.click()">Take picture</button>
    </div>
    <p class="mt-1 text-xs text-taupe" x-text="fileName ? fileName : 'Upload a clear photo, or take one now showing the problem or the item you received.'"></p>
    <img x-show="preview" x-cloak :src="preview" alt="Selected return photo" class="mt-3 max-h-48 w-full object-contain border border-beige bg-[#FFFCFA]">
    @error('photo')
        <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
    @enderror
</div>

<div>
    <label class="label" for="reason">Details <span class="normal-case tracking-wide text-[10px] font-normal text-blush">Required</span></label>
    <textarea id="reason" name="reason" class="input" rows="4" required minlength="8" placeholder="Describe the problem, what you ordered, and what you received.">{{ old('reason') }}</textarea>
    @error('reason')
        <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
    @enderror
</div>

<label class="flex items-start gap-3 text-sm">
    <input type="checkbox" name="policy_accepted" value="1" class="mt-1 h-4 w-4" @checked(old('policy_accepted')) required>
    <span>I confirm this return is for a real problem (defect, damage, or wrong item), and that the item was not used or broken after delivery.</span>
</label>
@error('policy_accepted')
    <p class="text-sm text-red-700">{{ $message }}</p>
@enderror
