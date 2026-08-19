@extends('layouts.admin')
@section('heading', 'Deleted products')
@section('title', 'Deleted products')
@section('content')
@include('admin.products._tabs', ['current' => 'trashed', 'activeCount' => $activeCount, 'inactiveCount' => $inactiveCount, 'trashedCount' => $trashedCount])

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

@if($products->isEmpty() && ! request()->filled('q') && ! request()->filled('gender'))
    <x-empty-state
        title="No deleted products"
        message="Deleted products will appear here so you can restore them or remove them forever."
        :action="route('admin.products.index')"
        actionLabel="Back to products"
    />
@else
    <x-admin.bulk-form
        :action="route('admin.products.bulk-force-destroy')"
        :ids="$products->pluck('id')"
        confirm="Permanently delete the selected products? This cannot be undone."
        label="Delete forever"
    >
        <x-slot:leading>
            <form
                method="POST"
                action="{{ route('admin.products.bulk-restore') }}"
                class="inline"
                @submit="if (!selected.length || !confirm('Restore the selected products?')) $event.preventDefault()"
            >
                @csrf
                <template x-for="id in selected" :key="'restore-'+id">
                    <input type="hidden" name="ids[]" :value="id">
                </template>
                <button
                    type="submit"
                    class="btn btn-secondary"
                    :disabled="!selected.length"
                    :class="!selected.length && 'opacity-40 cursor-not-allowed'"
                >
                    Restore selected
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
                        <th class="p-3">Deleted</th>
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
                        <td class="p-3 text-taupe">{{ $product->deleted_at?->format('Y-m-d H:i') }}</td>
                        <td class="p-3 text-right">
                            <div class="flex flex-wrap justify-end gap-2">
                                <form method="POST" action="{{ route('admin.products.restore', $product) }}">
                                    @csrf
                                    <button class="btn btn-secondary btn-sm" type="submit">Restore</button>
                                </form>
                                <form
                                    method="POST"
                                    action="{{ route('admin.products.force-destroy', $product) }}"
                                    onsubmit="return confirm('Permanently delete this product? This cannot be undone.')"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-danger btn-sm" type="submit">Delete forever</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="border-t border-beige">
                        <td class="p-6 text-center text-taupe" colspan="8">No deleted products match this search.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.bulk-form>
    <x-admin.pagination :paginator="$products" noun="product" />
@endif
@endsection
