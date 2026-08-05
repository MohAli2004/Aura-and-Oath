@props(['title' => null])
<div {{ $attributes->merge(['class' => 'border border-beige bg-[#FFFCFA] p-5']) }}>
    @if($title)<h3 class="font-display text-2xl mb-3">{{ $title }}</h3>@endif
    {{ $slot }}
</div>
