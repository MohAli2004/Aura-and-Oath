@props(['id'])

<input
    type="checkbox"
    value="{{ (int) $id }}"
    class="rounded border-beige text-charcoal focus:ring-gold shrink-0"
    :checked="isSelected({{ (int) $id }})"
    @change="toggle({{ (int) $id }}, $event.target.checked)"
    {{ $attributes }}
>
