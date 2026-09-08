@props(['class' => ''])

@php
    $locale = app()->getLocale();
@endphp

<nav {{ $attributes->merge(['class' => 'inline-flex items-center gap-0.5 text-xs tracking-wide '.$class]) }} aria-label="{{ __('storefront.language') }}">
    <form method="POST" action="{{ route('locale.update') }}" class="inline">
        @csrf
        <input type="hidden" name="locale" value="en">
        <button
            type="submit"
            class="px-2 py-1 rounded-sm transition-colors {{ $locale === 'en' ? 'text-charcoal font-medium' : 'text-taupe hover:text-charcoal' }}"
            @if($locale === 'en') aria-current="true" @endif
        >{{ __('storefront.language_en') }}</button>
    </form>
    <span class="text-beige select-none" aria-hidden="true">|</span>
    <form method="POST" action="{{ route('locale.update') }}" class="inline">
        @csrf
        <input type="hidden" name="locale" value="ar">
        <button
            type="submit"
            class="px-2 py-1 rounded-sm transition-colors {{ $locale === 'ar' ? 'text-charcoal font-medium' : 'text-taupe hover:text-charcoal' }}"
            @if($locale === 'ar') aria-current="true" @endif
        >{{ __('storefront.language_ar') }}</button>
    </form>
</nav>
