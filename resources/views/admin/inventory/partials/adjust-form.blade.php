@php
    $isOldForm = (string) old('product_id') === (string) $product->id
        && (string) (old('product_variant_id') ?? '') === (string) ($variant->id ?? '');
    $oldValue = $isOldForm ? (string) old('quantity_change', '') : '';
@endphp
<form
    method="POST"
    action="{{ route('admin.inventory.adjust') }}"
    class="flex flex-wrap items-center gap-1"
>
    @csrf
    <input type="hidden" name="product_id" value="{{ $product->id }}">
    @if($variant ?? null)
        <input type="hidden" name="product_variant_id" value="{{ $variant->id }}">
    @endif
    <div class="inline-flex items-stretch">
        <button
            type="button"
            class="btn btn-secondary px-2 py-1 text-sm leading-none"
            aria-label="Decrease adjustment"
            data-adjust-step="-1"
        >&minus;</button>
        <input
            class="input w-16 text-center px-1"
            type="text"
            name="quantity_change"
            inputmode="text"
            autocomplete="off"
            autocapitalize="off"
            spellcheck="false"
            placeholder="+/-"
            @if($oldValue !== '') value="{{ $oldValue }}" @endif
        >
        <button
            type="button"
            class="btn btn-secondary px-2 py-1 text-sm leading-none"
            aria-label="Increase adjustment"
            data-adjust-step="1"
        >+</button>
    </div>
    <button class="btn btn-secondary" type="submit">OK</button>
    @if($isOldForm && $errors->has('quantity_change'))
        <p class="basis-full text-xs text-red-700 mt-0.5">{{ $errors->first('quantity_change') }}</p>
    @endif
</form>
