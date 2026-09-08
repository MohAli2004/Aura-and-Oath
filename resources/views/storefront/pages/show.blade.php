@extends('layouts.storefront')
@php
    $seoTitle = $page->localized('meta_title') ?: $page->localized('title');
    $seoDescription = seo_truncate($page->localized('meta_description') ?: strip_tags((string) $page->localized('content')), 155);
@endphp
@section('title', $seoTitle)
@section('meta_description', $seoDescription)
@section('og_title', $seoTitle)
@section('canonical', route('pages.show', $page->slug))
@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 py-16">
    <h1 class="font-display text-5xl mb-6">{{ $page->localized('title') }}</h1>
    <div class="text-taupe leading-relaxed whitespace-pre-line">{{ $page->localized('content') }}</div>
</div>
@endsection
