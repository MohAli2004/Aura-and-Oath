@extends('layouts.admin')
@section('heading', 'Review import')
@section('title', 'Review import')
@section('content')
<div
    class="mb-6 flex flex-wrap items-end justify-between gap-3"
    x-data="{ status: @js($batch->status->value) }"
    x-init="if (status === 'processing') { const timer = setInterval(async () => { const response = await fetch(@js(route('admin.bulk-import.status', $batch)), { headers: { 'Accept': 'application/json' } }); const data = await response.json(); status = data.status; if (status !== 'processing') { clearInterval(timer); window.location.reload(); } }, 2000) }"
>
    <div>
        <p class="text-sm text-taupe">{{ $batch->original_filename }} · schema {{ $batch->schema_version }}</p>
        <p class="mt-1 text-sm">Status: <span x-text="status">{{ $batch->status->label() }}</span> · {{ $batch->success_count }} saved · {{ $batch->failure_count }} failed · {{ $batch->skipped_count }} skipped</p>
        @if($batch->error_summary)
            <p class="mt-2 text-sm text-red-700">{{ $batch->error_summary }}</p>
        @endif
    </div>
    <div class="flex flex-wrap gap-2">
        <a class="btn btn-secondary" href="{{ route('admin.bulk-import.download', $batch) }}">Original file</a>
        <a class="btn btn-secondary" href="{{ route('admin.bulk-import.editor') }}">Editor</a>
    </div>
</div>

@if($batch->source_meta)
    <p class="mb-4 text-sm text-taupe">Source: {{ $batch->source_meta['generator'] ?? 'unknown' }}. {{ $batch->source_meta['notes'] ?? 'AI content is unverified until you approve it.' }}</p>
@endif

<p class="mb-4 text-sm text-taupe">Each product, and each option, can take more than one image. Save the review before importing so the photos stay with that row.</p>

<form method="POST" action="{{ route('admin.bulk-import.update', $batch) }}" enctype="multipart/form-data" class="space-y-4">
    @csrf
    @method('PUT')
    <div class="overflow-x-auto border border-beige bg-[#FFFCFA]">
        <table class="w-full text-sm min-w-[980px]">
            <thead class="bg-beige/40 text-left">
                <tr>
                    <th class="p-3">Product</th>
                    <th class="p-3">Brand / category</th>
                    <th class="p-3">Variants</th>
                    <th class="p-3">Completeness</th>
                    <th class="p-3">Match</th>
                    <th class="p-3">Action</th>
                </tr>
            </thead>
            <tbody>
            @foreach($rows as $row)
                @php
                    $payload = $row->payload ?? [];
                    $missing = $row->missing_fields ?? [];
                @endphp
                <tr class="border-t border-beige align-top">
                    <td class="p-3">
                        <div class="font-medium">{{ $payload['name'] ?? $row->name }}</div>
                        @if(!empty($payload['name_ar']))
                            <div class="text-taupe" dir="rtl">{{ $payload['name_ar'] }}</div>
                        @endif
                        <div class="mt-2 grid gap-2">
                            <input class="input" name="rows[{{ $row->id }}][barcode]" value="{{ $payload['barcode'] ?? '' }}" placeholder="Barcode" @disabled($payload['has_variants'] ?? false)>
                            <div class="grid grid-cols-2 gap-2">
                                <input class="input" name="rows[{{ $row->id }}][price]" value="{{ $payload['price'] ?? '' }}" placeholder="Price" data-share-source="price" data-share-group="row-{{ $row->id }}">
                                <input class="input" name="rows[{{ $row->id }}][cost]" value="{{ $payload['cost'] ?? '' }}" placeholder="Cost" data-share-source="cost" data-share-group="row-{{ $row->id }}">
                                <input class="input" name="rows[{{ $row->id }}][compare_at_price]" value="{{ $payload['compare_at_price'] ?? '' }}" placeholder="Compare-at">
                                <input class="input" name="rows[{{ $row->id }}][stock]" value="{{ $payload['stock'] ?? '' }}" placeholder="Stock" data-share-source="stock" data-share-group="row-{{ $row->id }}">
                            </div>
                            @if($payload['has_variants'] ?? false)
                                <p class="text-xs text-taupe">Price, cost, and stock here are copied to every option. Change an option below if it should differ.</p>
                            @endif
                            <div class="flex flex-wrap gap-2">
                                @foreach($payload['staged_images'] ?? [] as $path)
                                    <label class="relative block h-20 w-20">
                                        <img src="{{ asset('storage/'.$path) }}" alt="" class="h-20 w-20 object-cover border border-beige bg-beige/30">
                                        <input type="checkbox" class="absolute left-1 top-1" name="rows[{{ $row->id }}][remove_images][]" value="{{ $path }}" title="Remove">
                                    </label>
                                @endforeach
                            </div>
                            <label class="label">Product images</label>
                            <label class="btn btn-secondary cursor-pointer">
                                Add images
                                <input class="sr-only" type="file" name="row_images[{{ $row->id }}][]" accept="image/*" multiple data-image-preview>
                            </label>
                            <div class="flex flex-wrap gap-2" data-image-preview-list></div>
                        </div>
                    </td>
                    <td class="p-3">
                        <div>{{ $payload['brand'] ?? '—' }}</div>
                        <div class="text-taupe">{{ implode(', ', $payload['categories'] ?? []) ?: '—' }}</div>
                    </td>
                    <td class="p-3">
                        {{ count($payload['variants'] ?? []) }}
                        @foreach($payload['variants'] ?? [] as $index => $variant)
                            <div class="mt-2 border border-beige p-2">
                                <div class="mb-1">{{ $variant['option_name'] ?? 'Option' }}</div>
                                <input class="input mb-1" name="rows[{{ $row->id }}][variants][{{ $index }}][barcode]" value="{{ $variant['barcode'] ?? '' }}" placeholder="Barcode">
                                <input class="input mb-1" name="rows[{{ $row->id }}][variants][{{ $index }}][price]" value="{{ $variant['price'] ?? '' }}" placeholder="Price" data-share-target="price" data-share-group="row-{{ $row->id }}">
                                <input class="input mb-1" name="rows[{{ $row->id }}][variants][{{ $index }}][cost]" value="{{ $variant['cost'] ?? '' }}" placeholder="Cost" data-share-target="cost" data-share-group="row-{{ $row->id }}">
                                <input class="input mb-1" name="rows[{{ $row->id }}][variants][{{ $index }}][stock]" value="{{ $variant['stock'] ?? '' }}" placeholder="Stock" data-share-target="stock" data-share-group="row-{{ $row->id }}">
                                <div class="mb-1 flex flex-wrap gap-2">
                                    @foreach($variant['staged_images'] ?? [] as $path)
                                        <label class="relative block h-20 w-20">
                                            <img src="{{ asset('storage/'.$path) }}" alt="" class="h-20 w-20 object-cover border border-beige bg-beige/30">
                                            <input type="checkbox" class="absolute left-1 top-1" name="rows[{{ $row->id }}][variants][{{ $index }}][remove_images][]" value="{{ $path }}" title="Remove">
                                        </label>
                                    @endforeach
                                </div>
                                <label class="label">Option images</label>
                                <label class="btn btn-secondary cursor-pointer">
                                    Add images
                                    <input class="sr-only" type="file" name="variant_images[{{ $row->id }}][{{ $index }}][]" accept="image/*" multiple data-image-preview>
                                </label>
                                <div class="mt-2 flex flex-wrap gap-2" data-image-preview-list></div>
                            </div>
                        @endforeach
                    </td>
                    <td class="p-3">
                        @forelse($missing as $field)
                            <div class="text-xs text-blush">{{ str_replace('_', ' ', $field) }}</div>
                        @empty
                            <span class="text-xs">Commercial fields present. Image and approval are still required.</span>
                        @endforelse
                        @foreach($row->errors ?? [] as $error)
                            <div class="mt-1 text-xs text-red-700">{{ $error }}</div>
                        @endforeach
                        @foreach($row->warnings ?? [] as $warning)
                            <div class="mt-1 text-xs text-taupe">{{ $warning }}</div>
                        @endforeach
                        @if($row->error_message)
                            <div class="mt-1 text-xs text-red-700">{{ $row->error_message }}</div>
                        @endif
                    </td>
                    <td class="p-3">
                        <div>{{ $row->match_type ?: 'none' }}</div>
                        @if($row->matchedProduct)
                            <a class="underline" href="{{ route('admin.products.edit', $row->matchedProduct) }}">{{ $row->matchedProduct->name }}</a>
                            <div class="text-xs text-taupe">{{ $row->matchedProduct->status->label() }}</div>
                        @endif
                        <div class="mt-1 text-xs">{{ $row->status->value }}</div>
                    </td>
                    <td class="p-3">
                        <select class="input" name="rows[{{ $row->id }}][resolution]">
                            @foreach($resolutions as $resolution)
                                <option value="{{ $resolution->value }}" @selected($row->resolution?->value === $resolution->value)>{{ $resolution->label() }}</option>
                            @endforeach
                        </select>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <x-admin.pagination :paginator="$rows" noun="product" />
    <div class="flex flex-wrap gap-2">
        <button class="btn btn-secondary" type="submit">Save review</button>
    </div>
</form>

<form method="POST" action="{{ route('admin.bulk-import.commit', $batch) }}" class="mt-3">
    @csrf
    <button class="btn btn-primary" type="submit">Import chosen rows as drafts</button>
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
@endsection
