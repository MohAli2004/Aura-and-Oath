@php
    $imageUrl = function (?string $path): ?string {
        if (! $path) {
            return null;
        }

        return str_starts_with($path, 'images/') ? asset($path) : asset('storage/'.$path);
    };
    $listQuery = array_filter([
        'filter' => $filter,
        'q' => $search,
        'page' => request('page') > 1 ? request('page') : null,
    ], fn ($value) => $value !== null && $value !== '');
@endphp

<a class="mb-4 inline-block text-sm underline" href="{{ route('admin.bulk-import.editor', $listQuery) }}">Back to the list</a>

<h2 class="font-display text-2xl mb-1">{{ $product->name }}</h2>
<p class="text-sm text-taupe mb-4">Only this product is open. Saving here does not reload the rest of the import.</p>

@if(session('publish_rejections'))
    <div class="mb-4 border border-beige bg-[#FFFCFA] p-3 text-sm">
        @foreach(session('publish_rejections') as $id => $reason)
            <div>Product {{ $id }}: {{ $reason }}</div>
        @endforeach
    </div>
@endif

<form method="POST" action="{{ route('admin.bulk-import.editor.save') }}" enctype="multipart/form-data" id="import-editor" class="border border-beige bg-[#FFFCFA] p-4">
    @csrf
    <div class="grid gap-6 lg:grid-cols-2">
        <div>
            <label class="label">Name</label>
            <input class="input mb-2" name="products[{{ $product->id }}][name]" value="{{ $product->name }}">
            <label class="label">Arabic name</label>
            <input class="input mb-2" dir="rtl" name="products[{{ $product->id }}][name_ar]" value="{{ $product->name_ar }}" placeholder="Arabic name">
            <label class="label">Description</label>
            <textarea class="input mb-2" rows="4" name="products[{{ $product->id }}][description]" placeholder="Description">{{ $product->description }}</textarea>
            <label class="label">Arabic description</label>
            <textarea class="input" rows="4" dir="rtl" name="products[{{ $product->id }}][description_ar]" placeholder="Arabic description">{{ $product->description_ar }}</textarea>
            <label class="mt-3 flex items-center gap-2 text-sm">
                <input type="checkbox" name="products[{{ $product->id }}][approve_content]" value="1" @checked($product->content_approved_at)>
                Approve content
            </label>
            <p class="mt-1 text-xs text-taupe">{{ $product->content_source?->label() ?? 'Unspecified' }} @if($product->import_meta['ai_unverified'] ?? false)· AI text is not a verified claim @endif</p>
            @if(!empty($product->import_meta['sources']))
                <ul class="mt-1 text-xs">
                    @foreach($product->import_meta['sources'] as $source)
                        <li>{{ $source['title'] ?? 'Source' }}</li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div>
            @if(!$product->has_variants)
                <label class="label">Barcode</label>
                <input class="input mb-2" name="products[{{ $product->id }}][barcode]" value="{{ $product->barcode }}" placeholder="Barcode">
                <label class="label">Price</label>
                <input class="input mb-2" name="products[{{ $product->id }}][price]" value="{{ $product->price }}" placeholder="Price">
                <label class="label">Cost</label>
                <input class="input mb-2" name="products[{{ $product->id }}][cost_price]" value="{{ $product->cost_price }}" placeholder="Cost">
                <label class="label">Compare-at</label>
                <input class="input mb-2" name="products[{{ $product->id }}][compare_at_price]" value="{{ $product->compare_at_price }}" placeholder="Compare-at (optional)">
                <label class="label">Stock</label>
                <input class="input" name="products[{{ $product->id }}][stock_quantity]" value="{{ $product->stock_quantity }}" placeholder="Stock, 0 allowed">
            @else
                <p class="text-xs text-taupe mb-2">Price, cost, and stock here are copied to every option. Change an option below if it should differ.</p>
                <input class="input mb-1" placeholder="Price for every option" data-share-source="price" data-share-group="product-{{ $product->id }}">
                <input class="input mb-1" placeholder="Cost for every option" data-share-source="cost" data-share-group="product-{{ $product->id }}">
                <input class="input mb-2" placeholder="Stock for every option" data-share-source="stock" data-share-group="product-{{ $product->id }}">
                @foreach($product->variants as $variant)
                    <div class="mb-2 border border-beige p-2">
                        <input type="hidden" name="products[{{ $product->id }}][variants][{{ $loop->index }}][id]" value="{{ $variant->id }}">
                        <input class="input mb-1" name="products[{{ $product->id }}][variants][{{ $loop->index }}][name]" value="{{ $variant->name }}" placeholder="Option">
                        <input class="input mb-1" dir="rtl" name="products[{{ $product->id }}][variants][{{ $loop->index }}][name_ar]" value="{{ $variant->name_ar }}" placeholder="Arabic option">
                        <input class="input mb-1" name="products[{{ $product->id }}][variants][{{ $loop->index }}][barcode]" value="{{ $variant->barcode }}" placeholder="Barcode">
                        <input class="input mb-1" name="products[{{ $product->id }}][variants][{{ $loop->index }}][price]" value="{{ $variant->price }}" placeholder="Price" data-share-target="price" data-share-group="product-{{ $product->id }}">
                        <input class="input mb-1" name="products[{{ $product->id }}][variants][{{ $loop->index }}][cost_price]" value="{{ $variant->cost_price }}" placeholder="Cost" data-share-target="cost" data-share-group="product-{{ $product->id }}">
                        <input class="input mb-1" name="products[{{ $product->id }}][variants][{{ $loop->index }}][compare_at_price]" value="{{ $variant->compare_at_price }}" placeholder="Compare-at">
                        <input class="input mb-1" name="products[{{ $product->id }}][variants][{{ $loop->index }}][stock_quantity]" value="{{ $variant->stock_quantity }}" placeholder="Stock" data-share-target="stock" data-share-group="product-{{ $product->id }}">
                        <label class="text-xs mr-2"><input type="checkbox" name="products[{{ $product->id }}][variants][{{ $loop->index }}][is_active]" value="1" @checked($variant->is_active)> Enabled</label>
                        <label class="text-xs mr-2"><input type="checkbox" name="products[{{ $product->id }}][variants][{{ $loop->index }}][is_default]" value="1" @checked($variant->is_default)> Show first</label>
                        <label class="text-xs"><input type="checkbox" name="products[{{ $product->id }}][variants][{{ $loop->index }}][remove]" value="1"> Remove</label>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach($variant->images as $image)
                                <label class="block text-xs">
                                    <img src="{{ $imageUrl($image->path) }}" alt="" class="mb-1 h-20 w-20 object-cover border border-beige bg-beige/30">
                                    <input type="checkbox" name="remove_image_ids[]" value="{{ $image->id }}"> Remove
                                </label>
                            @endforeach
                        </div>
                        <label class="label mt-2">Option images</label>
                        <label class="btn btn-secondary cursor-pointer">
                            Add images
                            <input class="sr-only" type="file" name="variant_images[{{ $variant->id }}][]" accept="image/*" multiple data-image-preview>
                        </label>
                        <div class="mt-2 flex flex-wrap gap-2" data-image-preview-list></div>
                    </div>
                @endforeach
                <div class="border border-dashed border-beige p-2">
                    <p class="text-xs mb-1">Add variant</p>
                    <input class="input mb-1" name="products[{{ $product->id }}][variants][{{ $product->variants->count() }}][name]" placeholder="New option name">
                    <input class="input mb-1" name="products[{{ $product->id }}][variants][{{ $product->variants->count() }}][barcode]" placeholder="Barcode">
                    <label class="label">Option images</label>
                    <label class="btn btn-secondary cursor-pointer">
                        Add images
                        <input class="sr-only" type="file" name="new_variant_images[{{ $product->id }}][]" accept="image/*" multiple data-image-preview>
                    </label>
                    <div class="mt-2 flex flex-wrap gap-2" data-image-preview-list></div>
                </div>
            @endif

            <div class="mt-4">
                <div class="mb-2 flex flex-wrap gap-2">
                    @foreach($product->images as $image)
                        <div class="text-xs">
                            <img src="{{ $imageUrl($image->path) }}" alt="" class="mb-1 h-20 w-20 object-cover border border-beige bg-beige/30">
                            <label class="mr-2">
                                <input type="radio" name="products[{{ $product->id }}][primary_image_id]" value="{{ $image->id }}" @checked($image->is_primary)>
                                Main
                            </label>
                            <label><input type="checkbox" name="remove_image_ids[]" value="{{ $image->id }}"> Remove</label>
                        </div>
                    @endforeach
                </div>
                <label class="label">Product images</label>
                <label class="btn btn-secondary cursor-pointer">
                    Add images
                    <input class="sr-only" type="file" name="images[{{ $product->id }}][]" accept="image/*" multiple data-image-preview>
                </label>
                <div class="mt-2 flex flex-wrap gap-2" data-image-preview-list></div>
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-3 sm:grid-cols-2">
        <div>
            <label class="label">Brand</label>
            <select class="input mb-2" name="products[{{ $product->id }}][brand_id]">
                <option value="">No brand</option>
                @foreach($brands as $brand)
                    <option value="{{ $brand->id }}" @selected($product->brand_id === $brand->id)>{{ $brand->name }}</option>
                @endforeach
            </select>
            <label class="label">Gender</label>
            <select class="input mb-2" name="products[{{ $product->id }}][gender]">
                @foreach(\App\Enums\ProductGender::cases() as $gender)
                    <option value="{{ $gender->value }}" @selected($product->gender === $gender)>{{ $gender->label() }}</option>
                @endforeach
            </select>
            <label class="label">Categories</label>
            <select class="input" name="products[{{ $product->id }}][categories][]" multiple size="4">
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected($product->categories->contains('id', $category->id))>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="text-sm">
            <div class="mb-2"><x-badge>{{ $product->status->label() }}</x-badge> {{ $product->visibility->label() }}</div>
            @forelse($product->readiness_issues ?? [] as $issue)
                <div class="text-blush text-xs">{{ \App\Services\ProductReadinessService::LABELS[$issue] ?? $issue }}</div>
            @empty
                <div class="text-xs">Ready to publish</div>
            @endforelse
            <a class="mt-3 inline-block text-sm underline" href="{{ route('admin.products.edit', $product) }}">Full product form</a>
        </div>
    </div>

    <button class="btn btn-primary mt-4" type="submit">Save this product</button>
</form>

<script nonce="{{ csp_nonce() }}">
document.addEventListener('input', (event) => {
    const source = event.target.closest('[data-share-source]');
    if (source) {
        document.querySelectorAll(`[data-share-target="${source.dataset.shareSource}"][data-share-group="${source.dataset.shareGroup}"]`).forEach((target) => {
            if (target.dataset.shareEdited === '1') {
                return;
            }
            target.value = source.value;
        });
        return;
    }
    const target = event.target.closest('[data-share-target]');
    if (target) {
        target.dataset.shareEdited = '1';
    }
});

document.addEventListener('change', (event) => {
    const input = event.target.closest('[data-image-preview]');
    if (!input) {
        return;
    }
    const list = input.closest('label')?.nextElementSibling;
    if (!list) {
        return;
    }
    (list.dataset.previewUrls || '').split(' ').filter(Boolean).forEach((url) => URL.revokeObjectURL(url));
    list.replaceChildren();
    const urls = [];
    Array.from(input.files || []).forEach((file) => {
        if (!file.type.startsWith('image/')) {
            return;
        }
        const url = URL.createObjectURL(file);
        urls.push(url);
        const image = document.createElement('img');
        image.src = url;
        image.alt = '';
        image.className = 'h-20 w-20 object-cover border border-beige bg-beige/30';
        list.appendChild(image);
    });
    list.dataset.previewUrls = urls.join(' ');
});
</script>
