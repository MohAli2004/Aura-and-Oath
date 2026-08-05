@props(['variant' => 'primary', 'type' => 'submit'])
<button type="{{ $type }}" {{ $attributes->merge(['class' => 'btn btn-'.$variant]) }}>
    {{ $slot }}
</button>
