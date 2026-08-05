@props([
    'action',
    'ids' => [],
    'confirm' => 'Delete the selected items? This cannot be undone.',
    'label' => 'Delete selected',
])

@php
    $ids = collect($ids)->map(fn ($id) => (int) $id)->values()->all();
@endphp

<div
    {{ $attributes }}
    x-data="{
        allIds: {{ \Illuminate\Support\Js::from($ids) }},
        selected: [],
        toggle(id, checked) {
            id = Number(id);
            if (checked) {
                if (!this.selected.includes(id)) this.selected.push(id);
            } else {
                this.selected = this.selected.filter((value) => value !== id);
            }
        },
        toggleAll(checked) {
            this.selected = checked ? [...this.allIds] : [];
        },
        isSelected(id) {
            return this.selected.includes(Number(id));
        },
        get allSelected() {
            return this.allIds.length > 0 && this.selected.length === this.allIds.length;
        },
        get someSelected() {
            return this.selected.length > 0 && this.selected.length < this.allIds.length;
        }
    }"
>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex flex-wrap items-center gap-3">
            <label class="inline-flex items-center gap-2 text-sm cursor-pointer select-none">
                <input
                    type="checkbox"
                    class="rounded border-beige text-charcoal focus:ring-gold"
                    :checked="allSelected"
                    x-effect="$el.indeterminate = someSelected"
                    @change="toggleAll($event.target.checked)"
                >
                <span>Select all</span>
            </label>

            <form
                method="POST"
                action="{{ $action }}"
                class="inline"
                @submit="if (!selected.length || !confirm({{ \Illuminate\Support\Js::from($confirm) }})) $event.preventDefault()"
            >
                @csrf
                @method('DELETE')
                <template x-for="id in selected" :key="id">
                    <input type="hidden" name="ids[]" :value="id">
                </template>
                <button
                    type="submit"
                    class="btn btn-danger"
                    :disabled="!selected.length"
                    :class="!selected.length && 'opacity-40 cursor-not-allowed'"
                >
                    {{ $label }}
                    <span x-show="selected.length" x-text="'(' + selected.length + ')'"></span>
                </button>
            </form>

            {{ $leading ?? '' }}
        </div>

        @if(isset($actions))
            <div class="flex flex-wrap items-center gap-2">
                {{ $actions }}
            </div>
        @endif
    </div>

    {{ $slot }}
</div>
