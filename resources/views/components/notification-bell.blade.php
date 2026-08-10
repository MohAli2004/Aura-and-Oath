@props([
    'feedUrl',
    'markReadUrl',
    'markAllUrl',
    'indexUrl',
    'align' => 'end',
])

@php
    $panelAlign = $align === 'start' ? 'start-0' : 'end-0';
@endphp

<div
    class="relative"
    x-data="{
        open: false,
        loading: false,
        unread: 0,
        items: [],
        feedUrl: @js($feedUrl),
        markReadUrl: @js($markReadUrl),
        markAllUrl: @js($markAllUrl),
        csrf: @js(csrf_token()),
        async refresh() {
            try {
                const res = await fetch(this.feedUrl, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (!res.ok) return;
                const data = await res.json();
                this.unread = data.unread_count ?? 0;
                this.items = data.notifications ?? [];
            } catch (e) {}
        },
        async toggle() {
            this.open = !this.open;
            if (this.open) {
                this.loading = true;
                await this.refresh();
                this.loading = false;
            }
        },
        async markRead(item) {
            if (item.unread) {
                try {
                    await fetch(this.markReadUrl.replace('__ID__', item.id), {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': this.csrf,
                            'Content-Type': 'application/json',
                        },
                        credentials: 'same-origin',
                        body: '{}',
                    });
                    item.unread = false;
                    if (this.unread > 0) this.unread--;
                } catch (e) {}
            }
            if (item.url) {
                window.location.href = item.url;
            }
        },
        async markAll() {
            try {
                await fetch(this.markAllUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': this.csrf,
                        'Content-Type': 'application/json',
                    },
                    credentials: 'same-origin',
                    body: '{}',
                });
                this.items = this.items.map(i => ({ ...i, unread: false }));
                this.unread = 0;
            } catch (e) {}
        },
    }"
    x-init="refresh()"
    @keydown.escape.window="open = false"
    @click.outside="open = false"
>
    <button
        type="button"
        class="relative inline-flex min-h-11 min-w-11 items-center justify-center rounded-sm border border-beige bg-[#FFFCFA] px-2.5 text-charcoal hover:bg-beige/40 transition-colors"
        @click="toggle()"
        :aria-expanded="open.toString()"
        aria-label="Notifications"
        title="Notifications"
    >
        <x-icon name="bell" class="w-5 h-5" />
        <span
            x-show="unread > 0"
            x-cloak
            class="absolute -top-1.5 -end-1.5 min-w-[1.15rem] rounded-full bg-blush px-1 py-0.5 text-center text-[10px] font-medium leading-none text-white"
            x-text="unread > 99 ? '99+' : unread"
        ></span>
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-1"
        class="absolute {{ $panelAlign }} top-full z-50 mt-2 w-[min(22rem,calc(100vw-1.5rem))] border border-beige bg-[#FFFCFA] shadow-md"
        role="menu"
        aria-label="Notifications list"
    >
        <div class="flex items-center justify-between gap-2 border-b border-beige px-3 py-2.5">
            <span class="font-display text-lg text-charcoal">Notifications</span>
            <button
                type="button"
                class="text-xs text-taupe hover:text-charcoal disabled:opacity-40"
                @click="markAll()"
                :disabled="unread === 0"
            >
                Mark all read
            </button>
        </div>

        <div class="max-h-80 overflow-y-auto">
            <template x-if="loading">
                <p class="px-3 py-6 text-center text-sm text-taupe">Loading…</p>
            </template>
            <template x-if="!loading && items.length === 0">
                <p class="px-3 py-8 text-center text-sm text-taupe">No notifications</p>
            </template>
            <template x-for="item in items" :key="item.id">
                <button
                    type="button"
                    class="flex w-full flex-col gap-0.5 border-b border-beige/80 px-3 py-3 text-start transition-colors hover:bg-beige/30"
                    :class="item.unread ? 'bg-ivory/80' : ''"
                    @click="markRead(item)"
                >
                    <span class="flex items-start justify-between gap-2">
                        <span class="text-sm font-medium text-charcoal" x-text="item.title"></span>
                        <span
                            x-show="item.unread"
                            class="mt-1 h-2 w-2 shrink-0 rounded-full bg-gold"
                            aria-hidden="true"
                        ></span>
                    </span>
                    <span class="text-xs leading-relaxed text-taupe" x-text="item.message"></span>
                    <span class="mt-1 text-[11px] text-taupe/80" x-text="item.created_at"></span>
                </button>
            </template>
        </div>

        <div class="border-t border-beige px-3 py-2.5">
            <a href="{{ $indexUrl }}" class="text-xs text-taupe underline hover:text-charcoal">View all notifications</a>
        </div>
    </div>
</div>
