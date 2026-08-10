@extends('layouts.admin')
@section('heading', 'Inventory')
@section('title', 'Inventory')
@section('content')
<div class="mb-6 border border-beige bg-[#FFFCFA] p-4">
    @if($unlocked)
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm font-medium">Inventory editing unlocked</p>
                <p class="text-xs text-taupe mt-1">
                    Expires {{ $unlockedUntil?->diffForHumans() ?? 'soon' }}.
                    Adjustments stay available while you keep working (refreshes for {{ \App\Support\InventoryLock::TTL_MINUTES }} minutes).
                </p>
            </div>
            <form method="POST" action="{{ route('admin.inventory.lock') }}">
                @csrf
                <button class="btn btn-secondary" type="submit">Lock now</button>
            </form>
        </div>
    @else
        <div class="max-w-md space-y-3">
            <div>
                <p class="text-sm font-medium">Inventory is locked</p>
                <p class="text-xs text-taupe mt-1">Enter your admin password to unlock stock adjustments.</p>
            </div>
            <form method="POST" action="{{ route('admin.inventory.unlock') }}" class="flex flex-wrap gap-2">
                @csrf
                <input
                    type="password"
                    name="password"
                    class="input max-w-xs"
                    placeholder="Admin password"
                    required
                    autocomplete="current-password"
                >
                <button class="btn btn-primary" type="submit">Unlock inventory</button>
            </form>
        </div>
    @endif
</div>

<form method="POST" action="{{ route('admin.inventory.scan') }}" class="mb-8 flex gap-2 max-w-xl" x-data>
    @csrf
    <input class="input" name="barcode" placeholder="Scan barcode and press Enter" autofocus required
           @keydown.enter="$el.form.submit()">
    <button class="btn btn-primary" type="submit">Lookup</button>
</form>

<form method="GET" class="mb-6 flex flex-wrap gap-2">
    <input class="input max-w-sm" name="q" value="{{ request('q') }}" placeholder="Search products">
    <button class="btn btn-secondary" type="submit">Search</button>
    @if(request()->filled('q'))
        <a href="{{ url()->current() }}" class="btn btn-secondary">Clear</a>
    @endif
</form>

<div class="grid lg:grid-cols-[1.4fr_1fr] gap-8">
    <div class="overflow-x-auto border border-beige bg-[#FFFCFA]">
        <table class="w-full text-sm">
            <thead class="bg-beige/40 text-left">
                <tr>
                    <th class="p-3">Product</th>
                    <th class="p-3">Stock</th>
                    <th class="p-3">Reserved</th>
                    <th class="p-3">Available</th>
                    <th class="p-3">Adjust</th>
                </tr>
            </thead>
            <tbody>
            @foreach($products as $product)
                <tr class="border-t border-beige">
                    <td class="p-3">
                        <a class="underline" href="{{ route('admin.products.edit', $product) }}">{{ $product->name }}</a>
                        <div class="text-taupe text-xs">{{ $product->sku }} · {{ $product->barcode }}</div>
                    </td>
                    <td class="p-3">{{ $product->stock_quantity }}</td>
                    <td class="p-3">{{ $product->reserved_quantity }}</td>
                    <td class="p-3">{{ $product->availableStock() }}</td>
                    <td class="p-3">
                        @if($unlocked)
                            <form method="POST" action="{{ route('admin.inventory.adjust') }}" class="flex gap-1">
                                @csrf
                                <input type="hidden" name="product_id" value="{{ $product->id }}">
                                <input class="input w-20" type="number" name="quantity_change" placeholder="+/-" required>
                                <button class="btn btn-secondary" type="submit">OK</button>
                            </form>
                        @else
                            <span class="text-xs text-taupe">Locked</span>
                        @endif
                    </td>
                </tr>
                @foreach($product->variants as $variant)
                    <tr class="border-t border-beige/60 bg-ivory/40">
                        <td class="p-3 ps-8 text-taupe">↳ {{ $variant->displayName() }} · {{ $variant->sku }}</td>
                        <td class="p-3">{{ $variant->stock_quantity }}</td>
                        <td class="p-3">{{ $variant->reserved_quantity }}</td>
                        <td class="p-3">{{ $variant->availableStock() }}</td>
                        <td class="p-3">
                            @if($unlocked)
                                <form method="POST" action="{{ route('admin.inventory.adjust') }}" class="flex gap-1">
                                    @csrf
                                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                                    <input type="hidden" name="product_variant_id" value="{{ $variant->id }}">
                                    <input class="input w-20" type="number" name="quantity_change" required>
                                    <button class="btn btn-secondary" type="submit">OK</button>
                                </form>
                            @else
                                <span class="text-xs text-taupe">Locked</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            @endforeach
            </tbody>
        </table>
    </div>
    <div>
        <h2 class="font-display text-2xl mb-3">Recent movements</h2>
        @foreach($movements as $m)
            <div class="border-b border-beige py-2 text-sm">
                <div>{{ $m->type->label() }} · {{ $m->quantity_change }}</div>
                <div class="text-taupe">{{ $m->product?->name }} · {{ $m->created_at->diffForHumans() }}</div>
            </div>
        @endforeach
    </div>
</div>
<div class="mt-6">{{ $products->links() }}</div>
@endsection
