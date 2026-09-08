@extends('layouts.admin')
@section('heading', 'Link page')
@section('title', 'Link page')
@section('content')
@php
    $buttonRows = $buttons->map(fn ($button) => [
        'id' => (int) $button->id,
        'label' => $button->label,
        'label_ar' => $button->label_ar,
        'url' => $button->url,
        'sort_order' => (int) $button->sort_order,
        'is_active' => $button->is_active,
        'open_in_new_tab' => $button->open_in_new_tab,
    ])->values();

    if (old('buttons')) {
        $buttonRows = collect(old('buttons'))->map(function ($row, $index) {
            return [
                'id' => isset($row['id']) && $row['id'] !== '' ? (int) $row['id'] : null,
                'label' => $row['label'] ?? '',
                'label_ar' => $row['label_ar'] ?? '',
                'url' => $row['url'] ?? '',
                'sort_order' => isset($row['sort_order']) ? (int) $row['sort_order'] : $index,
                'is_active' => filter_var($row['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'open_in_new_tab' => filter_var($row['open_in_new_tab'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ];
        })->values();
    }
@endphp

<form
    method="POST"
    action="{{ route('admin.link-hub.update') }}"
    class="max-w-4xl space-y-8"
    x-data="{
        rows: {{ \Illuminate\Support\Js::from($buttonRows) }},
        addRow() {
            this.rows.push({
                id: null,
                label: '',
                label_ar: '',
                url: '',
                sort_order: this.rows.length,
                is_active: true,
                open_in_new_tab: true,
            });
        },
        removeRow(index) {
            this.rows.splice(index, 1);
        }
    }"
>
    @csrf
    @method('PUT')

    <section class="border border-beige bg-[#FFFCFA] p-6 space-y-5">
        <div>
            <h2 class="font-display text-2xl">Page copy</h2>
            <p class="text-sm text-taupe mt-1">Headline and about text shown on the public link page (<a class="underline" href="{{ route('links.index') }}" target="_blank" rel="noopener">/links</a>).</p>
        </div>

        <x-input label="Headline (English)" name="headline" value="{{ old('headline', $settings->headline) }}" required requiredMark />
        <x-input label="Headline (Arabic)" name="headline_ar" value="{{ old('headline_ar', $settings->headline_ar) }}" hint="Optional. Shown when the storefront is in Arabic." />
        <div>
            <label class="label" for="body">About text (English)</label>
            <textarea id="body" name="body" class="input" rows="4">{{ old('body', $settings->body) }}</textarea>
        </div>
        <div>
            <label class="label" for="body_ar">About text (Arabic) <span class="normal-case tracking-wide text-[10px] font-normal text-taupe">Optional</span></label>
            <textarea id="body_ar" name="body_ar" class="input" rows="4">{{ old('body_ar', $settings->body_ar) }}</textarea>
        </div>
    </section>

    <section class="border border-beige bg-[#FFFCFA] p-6 space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="font-display text-2xl">Link buttons</h2>
                <p class="text-sm text-taupe mt-1">Stacked buttons for Instagram bio and other link hubs. URLs must start with <code class="text-xs">/</code>, <code class="text-xs">http://</code>, or <code class="text-xs">https://</code>. Empty URLs hide a button.</p>
            </div>
            <button type="button" class="btn btn-secondary" @click="addRow()">Add button</button>
        </div>

        <template x-if="rows.length === 0">
            <p class="text-sm text-taupe">No buttons yet. Add one above.</p>
        </template>

        <div class="space-y-4">
            <template x-for="(row, index) in rows" :key="index">
                <div class="border border-beige bg-ivory/40 p-4 space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-sm font-medium" x-text="'Button ' + (index + 1)"></span>
                        <button type="button" class="text-sm text-taupe underline hover:text-charcoal" @click="removeRow(index)">Remove</button>
                    </div>

                    <input type="hidden" :name="'buttons[' + index + '][id]'" x-model="row.id">

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="label">Label (English)</label>
                            <input type="text" class="input" :name="'buttons[' + index + '][label]'" x-model="row.label" required maxlength="100">
                        </div>
                        <div>
                            <label class="label">Label (Arabic) <span class="normal-case tracking-wide text-[10px] font-normal text-taupe">Optional</span></label>
                            <input type="text" class="input" :name="'buttons[' + index + '][label_ar]'" x-model="row.label_ar" maxlength="100">
                        </div>
                    </div>

                    <div>
                        <label class="label">URL</label>
                        <input type="text" class="input" :name="'buttons[' + index + '][url]'" x-model="row.url" required maxlength="500" placeholder="/shop or https://…">
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <div>
                            <label class="label">Sort order</label>
                            <input type="number" min="0" class="input" :name="'buttons[' + index + '][sort_order]'" x-model.number="row.sort_order">
                        </div>
                        <div class="flex items-end pb-1">
                            <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
                                <input type="checkbox" class="rounded border-beige" :name="'buttons[' + index + '][is_active]'" value="1" x-model="row.is_active">
                                Active
                            </label>
                        </div>
                        <div class="flex items-end pb-1">
                            <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
                                <input type="checkbox" class="rounded border-beige" :name="'buttons[' + index + '][open_in_new_tab]'" value="1" x-model="row.open_in_new_tab">
                                Open in new tab
                            </label>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </section>

    <div class="flex gap-3">
        <button type="submit" class="btn btn-primary">Save link page</button>
        <a href="{{ route('links.index') }}" class="btn btn-secondary" target="_blank" rel="noopener">Preview</a>
    </div>
</form>
@endsection
