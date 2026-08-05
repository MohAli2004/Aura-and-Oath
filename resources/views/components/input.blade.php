@props([
    'label' => null,
    'name' => null,
    'hint' => null,
    'requiredMark' => false,
])
@php
    $isRequired = filter_var($requiredMark, FILTER_VALIDATE_BOOLEAN) || $attributes->has('required');
    $mark = $isRequired ? 'Required' : 'Optional';
    $markClass = $isRequired ? 'text-blush' : 'text-taupe';
@endphp
<div>
    @if($label)
        <label class="label" @if($name) for="{{ $name }}" @endif>
            {{ $label }} <span class="normal-case tracking-wide text-[10px] font-normal {{ $markClass }}">{{ $mark }}</span>
        </label>
    @endif
    <input
        @if($name) name="{{ $name }}" id="{{ $name }}" @endif
        {{ $attributes->merge(['class' => 'input']) }}
    >
    @if($hint)
        <p class="mt-1 text-xs text-taupe leading-snug">{{ $hint }}</p>
    @endif
    @if($name && $errors->has($name))
        <p class="mt-1 text-sm text-red-700">{{ $errors->first($name) }}</p>
    @endif
</div>
