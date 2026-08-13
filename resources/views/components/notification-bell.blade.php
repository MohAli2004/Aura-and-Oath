@props([
    'feedUrl',
    'markReadUrl',
    'markAllUrl',
    'indexUrl',
    'align' => 'end',
    'pollMs' => 15000,
])

@php
    $panelAlign = $align === 'start' ? 'sm:start-0 sm:end-auto' : 'sm:end-0 sm:start-auto';
@endphp

<div
    class="relative z-10"
    :style="open ? { zIndex: 100001 } : null"
    x-data="{
        open: false,
        loading: false,
        unread: 0,
        items: [],
        justArrived: false,
        pollTimer: null,
        feedUrl: @js($feedUrl),
        markReadUrl: @js($markReadUrl),
        markAllUrl: @js($markAllUrl),
        pollMs: {{ (int) $pollMs }},
        async refresh({ silent = true } = {}) {
            try {
                if (! silent) this.loading = true;
                const res = await fetch(this.feedUrl, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    cache: 'no-store',
                });
                if (! res.ok) return;
                const data = await res.json();
                const nextUnread = data.unread_count ?? 0;
                if (nextUnread > this.unread) {
                    this.flashNew();
                }
                this.unread = nextUnread;
                this.items = data.notifications ?? [];
            } catch (e) {
            } finally {
                if (! silent) this.loading = false;
            }
        },
        flashNew() {
            this.justArrived = true;
            window.clearTimeout(this._flashTimer);
            this._flashTimer = window.setTimeout(() => { this.justArrived = false; }, 2200);
        },
        startPolling() {
            this.stopPolling();
            this.pollTimer = window.setInterval(() => {
                if (document.visibilityState === 'visible') {
                    this.refresh({ silent: true });
                }
            }, this.pollMs);
        },
        stopPolling() {
            if (this.pollTimer) {
                window.clearInterval(this.pollTimer);
                this.pollTimer = null;
            }
        },
        async toggle() {
            this.open = ! this.open;
            if (this.open) {
                await this.refresh({ silent: false });
            }
        },
        async markRead(item, { openUrl = false } = {}) {
            if (item.unread) {
                try {
                    const data = await window.auraHttp(this.markReadUrl.replace('__ID__', item.id), {
                        method: 'POST',
                        body: {},
                    });
                    item.unread = false;
                    this.unread = typeof data.unread_count === 'number'
                        ? data.unread_count
                        : Math.max(0, this.unread - 1);
                    window.dispatchEvent(new CustomEvent('aura:notifications-changed', {
                        detail: { unread_count: this.unread },
                    }));
                } catch (e) {}
            }

            if (openUrl && item.url) {
                this.open = false;
                window.location.href = item.url;
            }
        },
        async markAll() {
            if (this.unread === 0) return;
            try {
                await window.auraHttp(this.markAllUrl, { method: 'POST', body: {} });
                this.items = this.items.map((i) => ({ ...i, unread: false }));
                this.unread = 0;
                window.dispatchEvent(new CustomEvent('aura:notifications-changed', {
                    detail: { unread_count: 0 },
                }));
            } catch (e) {}
        },
    }"
    x-init="
        refresh({ silent: true });
        startPolling();
        const onVisible = () => {
            if (document.visibilityState === 'visible') refresh({ silent: true });
        };
        document.addEventListener('visibilitychange', onVisible);
        window.addEventListener('focus', onVisible);
        window.addEventListener('aura:notifications-changed', () => refresh({ silent: true }));
    "
    @keydown.escape.window="open = false"
    @click.outside="open = false"
>
    <button
        type="button"
        class="relative inline-flex min-h-11 min-w-11 items-center justify-center rounded-sm border px-2.5 transition-colors duration-200"
        :class="{
            'border-charcoal bg-charcoal text-ivory hover:bg-[#1a1918]': unread > 0,
            'border-beige bg-[#FFFCFA] text-charcoal hover:bg-beige/40': unread === 0,
            'bell-ring': justArrived,
        }"
        @click="toggle()"
        :aria-expanded="open.toString()"
        :aria-label="unread > 0 ? (unread + ' unread notification' + (unread === 1 ? '' : 's')) : 'Notifications'"
        title="Notifications"
    >
        <x-icon name="bell" class="w-5 h-5" />
        <span
            x-show="unread > 0"
            x-cloak
            x-transition.opacity
            class="absolute -top-1.5 -end-1.5 z-10 inline-flex min-w-[1.25rem] items-center justify-center rounded-full bg-[#B85C5C] px-1.5 py-0.5 text-center text-[11px] font-semibold leading-none text-white shadow-sm ring-2 ring-[#FFFCFA]"
            :class="justArrived ? 'scale-110' : ''"
            x-text="unread > 99 ? '99+' : unread"
        ></span>
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition.opacity
        class="fixed inset-0 bg-charcoal/25 sm:hidden"
        style="z-index: 100000;"
        @click="open = false"
        aria-hidden="true"
    ></div>

    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 translate-y-2 sm:translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-2 sm:translate-y-1"
        class="fixed inset-x-3 top-[max(0.75rem,env(safe-area-inset-top))] flex max-h-[min(32rem,calc(100dvh-1.5rem))] flex-col overflow-hidden border border-beige bg-[#FFFCFA] shadow-lg sm:absolute sm:inset-x-auto sm:top-full sm:mt-2 sm:max-h-[min(28rem,70vh)] sm:w-[min(22rem,calc(100vw-1.5rem))] sm:shadow-md {{ $panelAlign }}"
        style="z-index: 100001;"
        role="menu"
        aria-label="Notifications list"
        @click.stop
    >
        <div class="flex shrink-0 items-center justify-between gap-2 border-b border-beige px-3 py-2.5">
            <span class="min-w-0 font-display text-base text-charcoal sm:text-lg">
                Notifications
                <span
                    x-show="unread > 0"
                    x-cloak
                    class="ms-1 align-middle text-sm text-[#B85C5C]"
                    x-text="'(' + unread + ')'"
                ></span>
            </span>
            <div class="flex shrink-0 items-center gap-3">
                <button
                    type="button"
                    class="text-xs text-taupe hover:text-charcoal disabled:opacity-40"
                    @click="markAll()"
                    :disabled="unread === 0"
                >
                    Mark all read
                </button>
                <button
                    type="button"
                    class="inline-flex h-8 w-8 items-center justify-center text-taupe hover:text-charcoal sm:hidden"
                    @click="open = false"
                    aria-label="Close notifications"
                >
                    <x-icon name="close" class="w-4 h-4" />
                </button>
            </div>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain">
            <template x-if="loading">
                <p class="px-3 py-6 text-center text-sm text-taupe">Loading…</p>
            </template>
            <template x-if="!loading && items.length === 0">
                <p class="px-3 py-8 text-center text-sm text-taupe">No notifications</p>
            </template>
            <template x-for="item in items" :key="item.id">
                <div
                    class="border-b border-beige/80 px-3 py-3 transition-colors"
                    :class="item.unread ? 'bg-[#F7F3EE] border-s-2 border-s-[#B85C5C]' : ''"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <div class="break-words text-sm font-medium text-charcoal" x-text="item.title"></div>
                            <div class="mt-0.5 break-words text-xs leading-relaxed text-taupe" x-text="item.message"></div>
                            <div class="mt-1 text-[11px] text-taupe/80" x-text="item.created_at"></div>
                        </div>
                        <span
                            x-show="item.unread"
                            class="mt-1 h-2 w-2 shrink-0 rounded-full bg-[#B85C5C]"
                            aria-hidden="true"
                        ></span>
                    </div>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="text-xs text-taupe underline hover:text-charcoal disabled:no-underline disabled:opacity-40"
                            :disabled="!item.unread"
                            @click="markRead(item)"
                        >
                            Mark as read
                        </button>
                        <button
                            type="button"
                            class="text-xs text-charcoal underline"
                            x-show="item.url"
                            x-cloak
                            @click="markRead(item, { openUrl: true })"
                        >
                            Open
                        </button>
                    </div>
                </div>
            </template>
        </div>

        <div class="shrink-0 border-t border-beige px-3 py-2.5 pb-[max(0.625rem,env(safe-area-inset-bottom))]">
            <a href="{{ $indexUrl }}" class="text-xs text-taupe underline hover:text-charcoal" @click="open = false">View all notifications</a>
        </div>
    </div>
</div>
