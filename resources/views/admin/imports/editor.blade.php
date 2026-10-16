@extends('layouts.admin')
@section('heading', 'Import editor')
@section('title', 'Import editor')
@section('content')
@if($editing)
    @include('admin.imports._editor_product', ['product' => $editing, 'filter' => $filter, 'search' => $search, 'brands' => $brands, 'categories' => $categories])
@else
<p class="text-sm text-taupe max-w-3xl mb-4">The queue lists 40 products at a time. Open one product to edit its text, options, and images. AI descriptions stay marked as unverified until you approve them. A product missing a barcode, price, cost, stock, or image cannot leave Draft.</p>

<div class="mb-4 flex flex-wrap gap-2">
    @foreach($filters as $key => $label)
        <a href="{{ route('admin.bulk-import.editor', ['filter' => $key, 'q' => $search ?: null]) }}" class="btn {{ $filter === $key ? 'btn-primary' : 'btn-secondary' }}">{{ $label }}</a>
    @endforeach
</div>

<form method="GET" action="{{ route('admin.bulk-import.editor') }}" class="mb-4 flex flex-wrap gap-2">
    <input type="hidden" name="filter" value="{{ $filter }}">
    <input class="input max-w-md" name="q" value="{{ $search }}" placeholder="Search name, SKU, or barcode">
    <button class="btn btn-secondary" type="submit">Search</button>
    @if($search !== '')
        <a class="btn btn-secondary" href="{{ route('admin.bulk-import.editor', ['filter' => $filter]) }}">Clear</a>
    @endif
</form>

<div class="mb-4 flex flex-wrap gap-2">
    <a class="btn btn-secondary" href="{{ route('admin.bulk-import.export', ['format' => 'csv', 'filter' => $filter, 'q' => $search ?: null]) }}">Export CSV</a>
    <a class="btn btn-secondary" href="{{ route('admin.bulk-import.export', ['format' => 'xlsx', 'filter' => $filter, 'q' => $search ?: null]) }}">Export XLSX</a>
</div>

@if(session('publish_rejections'))
    <div class="mb-4 border border-beige bg-[#FFFCFA] p-3 text-sm">
        @foreach(session('publish_rejections') as $id => $reason)
            <div>Product {{ $id }}: {{ $reason }}</div>
        @endforeach
    </div>
@endif

<form method="POST" action="{{ route('admin.bulk-import.editor.bulk') }}" class="mb-4 grid gap-2 border border-beige bg-[#FFFCFA] p-3 sm:grid-cols-6">
    @csrf
    <input type="hidden" name="filter" value="{{ $filter }}">
    <input type="hidden" name="q" value="{{ $search }}">
    <input class="input" name="price" placeholder="Shared price">
    <input class="input" name="cost_price" placeholder="Shared cost">
    <input class="input" name="compare_at_price" placeholder="Shared compare-at">
    <input class="input" name="stock_quantity" placeholder="Shared stock">
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="variants" value="1" checked> Apply to variants</label>
    <div class="flex flex-wrap gap-2 sm:col-span-6">
        <button class="btn btn-secondary" type="submit" name="scope" value="selected" onclick="return copySelected(this.form)">Apply to selected</button>
        <button class="btn btn-secondary" type="submit" name="scope" value="filter" onclick="return confirm('Apply these values to every product in this filter, including products on other pages?')">Apply to all in this filter</button>
    </div>
</form>

<div class="overflow-x-auto border border-beige bg-[#FFFCFA]">
    <table class="w-full text-sm">
        <thead class="bg-beige/40 text-left">
            <tr>
                <th class="p-3 w-10"><input type="checkbox" onclick="document.querySelectorAll('[data-select]').forEach(box => box.checked = this.checked)"></th>
                <th class="p-3">Product</th>
                <th class="p-3">Barcode</th>
                <th class="p-3">Price</th>
                <th class="p-3">Stock</th>
                <th class="p-3">Status</th>
                <th class="p-3">Missing</th>
                <th class="p-3"></th>
            </tr>
        </thead>
        <tbody>
        @forelse($products as $product)
            <tr class="border-t border-beige">
                <td class="p-3"><input data-select type="checkbox" value="{{ $product->id }}" class="publish-id"></td>
                <td class="p-3 min-w-[12rem]">
                    <div>{{ $product->name }}</div>
                    @if($product->name_ar)
                        <div class="text-xs text-taupe" dir="rtl">{{ $product->name_ar }}</div>
                    @endif
                    <div class="text-xs text-taupe">{{ $product->sku }}</div>
                </td>
                <td class="p-3">
                    @if($product->has_variants)
                        {{ $product->variants_count }} {{ \Illuminate\Support\Str::plural('option', $product->variants_count) }}
                    @else
                        {{ $product->barcode ?: '—' }}
                    @endif
                </td>
                <td class="p-3">{{ $product->has_variants ? 'Options' : ($product->price !== null ? money($product->price) : '—') }}</td>
                <td class="p-3">{{ $product->has_variants ? 'Options' : ($product->stock_quantity !== null ? $product->stock_quantity : '—') }}</td>
                <td class="p-3"><x-badge>{{ $product->status->label() }}</x-badge></td>
                <td class="p-3 text-xs">
                    @php $issues = $product->readiness_issues ?? []; @endphp
                    @forelse(array_slice($issues, 0, 3) as $issue)
                        <div class="text-blush">{{ \App\Services\ProductReadinessService::LABELS[$issue] ?? $issue }}</div>
                    @empty
                        <div>Ready to publish</div>
                    @endforelse
                    @if(count($issues) > 3)
                        <div class="text-taupe">+{{ count($issues) - 3 }} more</div>
                    @endif
                </td>
                <td class="p-3 text-right">
                    <a class="underline" href="{{ route('admin.bulk-import.editor', ['filter' => $filter, 'q' => $search ?: null, 'page' => $products->currentPage() > 1 ? $products->currentPage() : null, 'product' => $product->id]) }}">Edit</a>
                </td>
            </tr>
        @empty
            <tr><td class="p-3 text-taupe" colspan="8">No imported products in this filter.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<x-admin.pagination :paginator="$products" noun="product" />

<form method="POST" action="{{ route('admin.bulk-import.editor.publish') }}" class="mt-3" id="publish-form">
    @csrf
    <button class="btn btn-secondary" type="submit" onclick="return copySelected(this.form)">Publish selected</button>
</form>

@if($media->isNotEmpty())
    <section class="mt-8">
        <h2 class="font-display text-2xl mb-3">Image review</h2>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($media as $file)
                <form method="POST" action="{{ route('admin.bulk-import.media.assign', $file) }}" class="border border-beige bg-[#FFFCFA] p-3 text-sm">
                    @csrf
                    <img src="{{ asset('storage/'.$file->stored_path) }}" alt="" class="mb-2 h-24 w-full object-cover bg-beige/30">
                    <div class="truncate">{{ $file->original_filename }}</div>
                    <div class="text-xs text-taupe mb-2">{{ $file->match_status }} {{ $file->note }}</div>
                    <input class="input mb-2" name="product_id" placeholder="Product id" value="{{ $file->product_id }}">
                    <input class="input mb-2" name="product_variant_id" placeholder="Variant id (optional)" value="{{ $file->product_variant_id }}">
                    <button class="btn btn-secondary" type="submit">Assign</button>
                </form>
            @endforeach
        </div>
    </section>
@endif

<script nonce="{{ csp_nonce() }}">
function copySelected(form) {
    form.querySelectorAll('[data-copied]').forEach((node) => node.remove());
    document.querySelectorAll('.publish-id:checked').forEach((box) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ids[]';
        input.value = box.value;
        input.setAttribute('data-copied', '1');
        form.appendChild(input);
    });
    if (!form.querySelector('[data-copied]')) {
        alert('Select at least one product.');
        return false;
    }
    return true;
}
</script>
@endif
@endsection
