<div
    class="pointer-events-none fixed inset-x-0 z-[100000] flex justify-center px-3 sm:justify-end sm:px-6"
    style="bottom: max(1rem, calc(env(safe-area-inset-bottom, 0px) + 0.75rem));"
    x-data="{
        show: false,
        message: '',
        type: 'success',
        timer: null,
        onToast(event) {
            this.message = event.detail?.message || '';
            this.type = event.detail?.type || 'success';
            if (! this.message) return;
            this.show = true;
            window.clearTimeout(this.timer);
            this.timer = window.setTimeout(() => { this.show = false; }, 3200);
        },
    }"
    @aura:toast.window="onToast($event)"
>
    <div
        x-show="show"
        x-cloak
        x-transition.opacity.duration.200ms
        class="pointer-events-auto w-full max-w-sm border px-4 py-3 text-sm leading-snug"
        :class="type === 'error'
            ? 'border-blush bg-[#F7EEEE] text-charcoal'
            : 'border-beige bg-[#FFFCFA] text-charcoal'"
        role="status"
        x-text="message"
    ></div>
</div>
