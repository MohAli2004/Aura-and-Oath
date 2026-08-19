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
</div>
