@extends('layouts.storefront')
@php
    $seoTitle = $page->meta_title ?: $page->title;
    $seoDescription = $page->meta_description ?: Str::limit(strip_tags((string) $page->content), 160);
@endphp
@section('title', $seoTitle)
@section('meta_description', $seoDescription)
@section('og_title', $seoTitle)
@section('canonical', route('pages.show', $page->slug))
@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 py-16">
    <h1 class="font-display text-5xl mb-6">{{ $page->title }}</h1>
    <div class="text-taupe leading-relaxed whitespace-pre-line">{{ $page->content }}</div>
</div>
@endsection
