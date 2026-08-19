@props(['paginator', 'noun' => 'result'])

@php
    $total = (int) $paginator->total();
    $label = \Illuminate\Support\Str::plural($noun, $total);
@endphp

<div {{ $attributes->merge(['class' => 'mt-6 mb-10 pb-6 flex flex-col items-center gap-3 text-center sm:mb-6 sm:pb-0']) }}>
    <p class="text-sm text-taupe">
        @if ($total === 0)
            0 {{ $label }}
        @elseif ($paginator->hasPages())
            Showing
            <span class="font-medium text-charcoal">{{ $paginator->firstItem() }}</span>
            –
            <span class="font-medium text-charcoal">{{ $paginator->lastItem() }}</span>
            of
            <span class="font-medium text-charcoal">{{ $paginator->total() }}</span>
            {{ $label }}
        @else
            <span class="font-medium text-charcoal">{{ $total }}</span>
            {{ $label }}
        @endif
    </p>

    @if ($paginator->hasPages())
        <div class="overflow-x-auto">
            {{ $paginator->onEachSide(1)->links('pagination::admin') }}
        </div>
    @endif
</div>
