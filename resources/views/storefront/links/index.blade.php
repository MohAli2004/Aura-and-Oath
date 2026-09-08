@extends('layouts.links')

@section('title', __('storefront.links_page_title'))
@section('meta_description', seo_truncate($settings->localized('body') ?: config('aura.tagline', ''), 155))
@section('canonical', route('links.index'))

@section('content')
<div class="w-full text-center mb-8">
    <h1 class="font-display text-3xl sm:text-4xl mb-3">{{ $settings->localized('headline') }}</h1>
    @if($settings->localized('body'))
        <p class="text-taupe leading-relaxed text-sm sm:text-base">{{ $settings->localized('body') }}</p>
    @endif
</div>

<div class="w-full space-y-3">
    @foreach($buttons as $button)
        @php
            $href = $button->href();
            $external = $button->open_in_new_tab && ! str_starts_with($href, '/');
        @endphp
        <a
            href="{{ $href }}"
            class="btn btn-primary w-full text-center block py-3.5"
            @if($external) target="_blank" rel="noopener noreferrer" @endif
        >{{ $button->localized('label') }}</a>
    @endforeach
</div>

<p class="mt-10 text-xs text-taupe text-center">
    <a href="{{ route('home') }}" class="underline decoration-beige underline-offset-2 hover:text-charcoal">{{ config('aura.name') }}</a>
</p>
@endsection
