@props([
    'name',
    'label',
    'checked' => false,
    'hint' => null,
    'id' => null,
])
@php
    $id = $id ?: 'toggle-'.str_replace(['[', ']'], ['-', ''], $name);
    $isOn = filter_var($checked, FILTER_VALIDATE_BOOLEAN);
@endphp
<div class="admin-toggle">
    <input type="hidden" name="{{ $name }}" value="0">
    <label class="admin-toggle__row">
        <span class="admin-toggle__copy">
            <span class="admin-toggle__label">{{ $label }}</span>
            @if($hint)
                <span class="admin-toggle__hint">{{ $hint }}</span>
            @endif
        </span>
        <span class="admin-toggle__switch">
            <input
                type="checkbox"
                id="{{ $id }}"
                name="{{ $name }}"
                value="1"
                class="admin-toggle__input"
                @checked($isOn)
                {{ $attributes }}
            >
            <span class="admin-toggle__track" aria-hidden="true">
                <span class="admin-toggle__thumb"></span>
            </span>
        </span>
    </label>
</div>
