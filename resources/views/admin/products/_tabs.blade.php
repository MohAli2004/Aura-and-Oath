@php
    $current = $current ?? 'active';
    $activeCount = $activeCount ?? 0;
    $inactiveCount = $inactiveCount ?? 0;
    $trashedCount = $trashedCount ?? 0;
@endphp
<div class="flex flex-wrap gap-2 mb-6">
    <a
        href="{{ route('admin.products.index') }}"
        class="btn {{ $current === 'active' ? 'btn-primary' : 'btn-secondary' }}"
    >
        Products
        <span class="opacity-70">({{ $activeCount }})</span>
    </a>
    <a
        href="{{ route('admin.products.inactive') }}"
        class="btn {{ $current === 'inactive' ? 'btn-primary' : 'btn-secondary' }}"
    >
        Inactive products
        <span class="opacity-70">({{ $inactiveCount }})</span>
    </a>
    <a
        href="{{ route('admin.products.trashed') }}"
        class="btn {{ $current === 'trashed' ? 'btn-primary' : 'btn-secondary' }}"
    >
        Deleted products
        <span class="opacity-70">({{ $trashedCount }})</span>
    </a>
    @if($current !== 'trashed')
        <a href="{{ route('admin.products.export') }}" class="btn btn-secondary">Export Excel</a>
        <form method="POST" action="{{ route('admin.products.import') }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2">
            @csrf
            <input class="input max-w-[14rem]" type="file" name="file" accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv" required>
            <button class="btn btn-secondary" type="submit">Import Excel</button>
        </form>
    @endif
</div>
<p class="mb-6 -mt-3 text-xs text-taupe">Export includes variants. Import the same file to update products by barcode and add rows whose barcodes are not in the catalogue.</p>
