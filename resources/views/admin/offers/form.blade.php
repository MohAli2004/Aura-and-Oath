@extends('layouts.admin')
@section('heading', $offer->exists ? 'Edit offer' : 'New offer')
@section('title', $offer->exists ? 'Edit offer' : 'New offer')
@section('content')
@php
    $catalogPayload = $catalog->map(fn ($product) => [
        'id' => (int) $product->id,
        'name' => $product->name,
        'price' => (float) $product->price,
    ])->values();

    $selectedPayload = $selected->map(fn ($product) => [
        'id' => (int) $product->id,
        'name' => $product->name,
        'price' => (float) $product->price,
        'offer_price' => (float) ($product->pivot->offer_price ?? $product->price),
    ])->values();

    if (old('products')) {
        $selectedPayload = collect(old('products'))->map(function ($row) use ($catalogPayload) {
            $id = (int) ($row['id'] ?? 0);
            $match = $catalogPayload->firstWhere('id', $id);

            return [
                'id' => $id,
                'name' => $match['name'] ?? ('Product #'.$id),
                'price' => (float) ($match['price'] ?? 0),
                'offer_price' => (float) ($row['offer_price'] ?? 0),
            ];
        })->values();
    }
@endphp

<form
    method="POST"
    action="{{ $offer->exists ? route('admin.offers.update', $offer) : route('admin.offers.store') }}"
    class="max-w-3xl space-y-5"
    x-data="{
        catalog: {{ \Illuminate\Support\Js::from($catalogPayload) }},
        selected: {{ \Illuminate\Support\Js::from($selectedPayload) }},
        query: '',
        pickId: '',
        get filtered() {
            const q = this.query.trim().toLowerCase();
            const taken = this.selected.map((item) => Number(item.id));
            return this.catalog.filter((item) => !taken.includes(Number(item.id)) && (!q || item.name.toLowerCase().includes(q))).slice(0, 12);
        },
        add(id) {
            id = Number(id || this.pickId);
            const product = this.catalog.find((item) => Number(item.id) === id);
            if (!product) return;
            if (this.selected.some((item) => Number(item.id) === id)) return;
            this.selected.push({
                id: product.id,
                name: product.name,
                price: product.price,
                offer_price: product.price,
            });
            this.pickId = '';
            this.query = '';
        },
        remove(id) {
            this.selected = this.selected.filter((item) => Number(item.id) !== Number(id));
        }
    }"
>
    @csrf
    @if($offer->exists) @method('PUT') @endif

    <x-input label="Title" name="title" value="{{ old('title', $offer->title) }}" required requiredMark />
    <x-input label="Slug" name="slug" value="{{ old('slug', $offer->slug) }}" hint="Leave empty to generate from the title." />
    <div>
        <label class="label" for="description">Description <span class="normal-case tracking-wide text-[10px] font-normal text-taupe">Optional</span></label>
        <textarea id="description" name="description" class="input" rows="3">{{ old('description', $offer->description) }}</textarea>
    </div>
    <div class="grid sm:grid-cols-2 gap-4">
        <x-input label="Starts" name="starts_at" type="datetime-local" value="{{ old('starts_at', $offer->starts_at?->format('Y-m-d\TH:i')) }}" />
        <x-input label="Ends" name="ends_at" type="datetime-local" value="{{ old('ends_at', $offer->ends_at?->format('Y-m-d\TH:i')) }}" />
    </div>
    <x-input label="Sort order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $offer->sort_order ?? 0) }}" />
    <label class="flex gap-2 text-sm">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $offer->is_active ?? true))>
        Visible to customers
    </label>

    <div class="border border-beige bg-[#FFFCFA] p-4 space-y-3">
        <div>
            <h2 class="font-display text-2xl">Products</h2>
            <p class="text-sm text-taupe">Add products and set a special offer price for each one.</p>
        </div>

        <div class="flex flex-wrap gap-2">
            <input class="input max-w-sm" x-model="query" placeholder="Search products" @keydown.enter.prevent="filtered[0] && add(filtered[0].id)">
            <select class="input max-w-xs" x-model="pickId">
                <option value="">Choose a product</option>
                <template x-for="item in filtered" :key="item.id">
                    <option :value="item.id" x-text="item.name + ' · ' + item.price.toFixed(2)"></option>
                </template>
            </select>
            <button type="button" class="btn btn-secondary" @click="add()">Add</button>
        </div>
        @error('products')
            <p class="text-sm text-red-700">{{ $message }}</p>
        @enderror

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-taupe">
                    <tr>
                        <th class="py-2">Product</th>
                        <th class="py-2">Regular</th>
                        <th class="py-2">Offer price</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(item, index) in selected" :key="item.id">
                        <tr class="border-t border-beige">
                            <td class="py-2">
                                <input type="hidden" :name="'products['+index+'][id]'" :value="item.id">
                                <span x-text="item.name"></span>
                            </td>
                            <td class="py-2 text-taupe" x-text="item.price.toFixed(2)"></td>
                            <td class="py-2">
                                <input class="input w-28" type="number" min="0" step="0.01" :name="'products['+index+'][offer_price]'" x-model="item.offer_price">
                            </td>
                            <td class="py-2 text-right">
                                <button type="button" class="underline" @click="remove(item.id)">Remove</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <p class="text-sm text-taupe mt-3" x-show="selected.length === 0">Add at least one product.</p>
        </div>
    </div>

    <div class="flex flex-wrap gap-3">
        <button class="btn btn-primary" type="submit">Save offer</button>
        <a class="btn btn-secondary" href="{{ route('admin.offers.index') }}">Cancel</a>
    </div>
</form>

@if($offer->exists)
    <form class="mt-6" method="POST" action="{{ route('admin.offers.destroy', $offer) }}">
        @csrf @method('DELETE')
        <button class="btn btn-danger" onclick="return confirm('Delete this offer?')">Delete</button>
    </form>
@endif
@endsection
