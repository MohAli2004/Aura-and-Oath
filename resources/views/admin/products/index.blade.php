@extends('layouts.admin')
@section('heading', 'Products')
@section('title', 'Products')
@section('content')
<div class="mb-6">
    <form method="GET" class="flex flex-wrap gap-2">
        <input class="input" name="q" value="{{ request('q') }}" placeholder="Search SKU, name, barcode">
        <select name="gender" class="input max-w-[10rem]">
            <option value="">All genders</option>
            @foreach(\App\Enums\ProductGender::cases() as $gender)
                <option value="{{ $gender->value }}" @selected(request('gender')===$gender->value)>{{ $gender->label() }}</option>
            @endforeach
        </select>
        <button class="btn btn-secondary" type="submit">Search</button>
        @if(request()->filled('q') || request()->filled('gender'))
            <a href="{{ url()->current() }}" class="btn btn-secondary">Clear</a>
        @endif
    </form>
</div>

<x-admin.bulk-form
    :action="route('admin.products.bulk-destroy')"
    :ids="$products->pluck('id')"
    confirm="Delete the selected products? This cannot be undone."
    label="Delete selected"
>
    <x-slot:actions>
        <a href="{{ route('admin.products.create') }}" class="btn btn-primary">Add product</a>
    </x-slot:actions>

    <div class="overflow-x-auto border border-beige bg-[#FFFCFA]">
        <table class="w-full text-sm">
            <thead class="bg-beige/40 text-left">
                <tr>
                    <th class="p-3 w-10"></th>
                    <th class="p-3">Product</th><th class="p-3">SKU</th><th class="p-3">Barcode</th><th class="p-3">Gender</th><th class="p-3">Price</th><th class="p-3">Stock</th><th class="p-3">Status</th><th class="p-3"></th>
                </tr>
            </thead>
            <tbody>
            @foreach($products as $product)
                @php
                    $img = $product->primaryImagePath();
                    $imgUrl = $img
                        ? (str_starts_with($img, 'images/') ? asset($img) : asset('storage/'.$img))
                        : null;
                @endphp
                <tr class="border-t border-beige">
                    <td class="p-3"><x-admin.bulk-checkbox :id="$product->id" /></td>
                    <td class="p-3">
                        <div class="flex items-center gap-3 min-w-0">
                            @if($imgUrl)
                                <img src="{{ $imgUrl }}" alt="" class="h-10 w-10 shrink-0 object-cover border border-beige bg-beige/30">
                            @endif
                            <span class="truncate">{{ $product->name }}</span>
                        </div>
                    </td>
                    <td class="p-3">{{ $product->sku }}</td>
                    <td class="p-3">{{ $product->barcode }}</td>
                    <td class="p-3">{{ $product->gender?->label() ?? '—' }}</td>
                    <td class="p-3">{{ money($product->price) }}</td>
                    <td class="p-3">{{ $product->availableStock() }}</td>
                    <td class="p-3"><x-badge>{{ $product->status->label() }}</x-badge></td>
                    <td class="p-3 text-right"><a class="underline" href="{{ route('admin.products.edit', $product) }}">Edit</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</x-admin.bulk-form>
<div class="mt-6">{{ $products->links() }}</div>
@endsection
