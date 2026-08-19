@extends('layouts.admin')
@section('heading', 'Inactive products')
@section('title', 'Inactive products')
@section('content')
@include('admin.products._tabs', ['current' => 'inactive', 'activeCount' => $activeCount, 'inactiveCount' => $inactiveCount, 'trashedCount' => $trashedCount])

<div class="mb-6">
    <form method="GET" class="flex flex-wrap gap-2">
        <input class="input" name="q" value="{{ request('q') }}" placeholder="Search SKU, name, barcode">
        <select name="status" class="input max-w-[10rem]">
            <option value="">All inactive</option>
            <option value="{{ \App\Enums\ProductStatus::Draft->value }}" @selected(request('status')===\App\Enums\ProductStatus::Draft->value)>Draft</option>
            <option value="{{ \App\Enums\ProductStatus::Archived->value }}" @selected(request('status')===\App\Enums\ProductStatus::Archived->value)>Archived</option>
        </select>
        <select name="gender" class="input max-w-[10rem]">
            <option value="">All genders</option>
            @foreach(\App\Enums\ProductGender::cases() as $gender)
                <option value="{{ $gender->value }}" @selected(request('gender')===$gender->value)>{{ $gender->label() }}</option>
            @endforeach
        </select>
        <button class="btn btn-secondary" type="submit">Search</button>
        @if(request()->filled('q') || request()->filled('gender') || request()->filled('status'))
            <a href="{{ url()->current() }}" class="btn btn-secondary">Clear</a>
        @endif
    </form>
</div>

@if($products->isEmpty() && ! request()->filled('q') && ! request()->filled('gender') && ! request()->filled('status'))
    <x-empty-state
        title="No inactive products"
        message="Draft and archived products will appear here. Activate them to put them back in the catalog."
        :action="route('admin.products.index')"
        actionLabel="Back to products"
    />
@else
    <x-admin.bulk-form
        :action="route('admin.products.bulk-destroy')"
        :ids="$products->pluck('id')"
        confirm="Move the selected products to Deleted products? You can restore them later."
        label="Delete selected"
    >
        <x-slot:leading>
            <form
                method="POST"
                action="{{ route('admin.products.bulk-activate') }}"
                class="inline"
                @submit="if (!selected.length || !confirm('Activate the selected products?')) $event.preventDefault()"
            >
                @csrf
                <template x-for="id in selected" :key="'activate-'+id">
                    <input type="hidden" name="ids[]" :value="id">
                </template>
                <button
                    type="submit"
                    class="btn btn-secondary"
                    :disabled="!selected.length"
                    :class="!selected.length && 'opacity-40 cursor-not-allowed'"
                >
                    Activate selected
                    <span x-show="selected.length" x-text="'(' + selected.length + ')'"></span>
                </button>
            </form>
        </x-slot:leading>

        <div class="overflow-x-auto border border-beige bg-[#FFFCFA]">
            <table class="w-full text-sm">
                <thead class="bg-beige/40 text-left">
                    <tr>
                        <th class="p-3 w-10"></th>
                        <th class="p-3">Product</th>
                        <th class="p-3">SKU</th>
                        <th class="p-3">Barcode</th>
                        <th class="p-3">Gender</th>
                        <th class="p-3">Price</th>
                        <th class="p-3">Stock</th>
                        <th class="p-3">Status</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($products as $product)
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
                        <td class="p-3 text-right">
                            <div class="flex flex-wrap justify-end gap-2">
                                <form method="POST" action="{{ route('admin.products.activate', $product) }}">
                                    @csrf
                                    <button class="btn btn-secondary btn-sm" type="submit">Activate</button>
                                </form>
                                <a class="underline self-center" href="{{ route('admin.products.edit', $product) }}">Edit</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="border-t border-beige">
                        <td class="p-6 text-center text-taupe" colspan="9">No inactive products match this search.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.bulk-form>
    <x-admin.pagination :paginator="$products" noun="product" />
@endif
@endsection
