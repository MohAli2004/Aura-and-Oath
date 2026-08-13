<div
    class="pointer-events-none fixed inset-x-0 bottom-4 z-[100000] flex justify-center px-4 sm:justify-end sm:px-6"
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
            this.timer = window.setTimeout(() => { this.show = false; }, 2800);
        },
    }"
    @aura:toast.window="onToast($event)"
>
    <div
        x-show="show"
        x-cloak
        x-transition.opacity.duration.200ms
        class="pointer-events-auto max-w-sm border px-4 py-3 text-sm shadow-sm"
        :class="type === 'error'
            ? 'border-blush bg-[#F7EEEE] text-charcoal'
            : 'border-beige bg-[#FFFCFA] text-charcoal'"
        role="status"
        x-text="message"
    ></div>
</div>
