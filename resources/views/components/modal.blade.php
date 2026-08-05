@props(['name' => 'modal', 'title' => ''])
<div x-data="{ open: false }" @open-{{ $name }}.window="open = true" @keydown.escape.window="open = false">
    <template x-teleport="body">
        <div x-show="open" class="fixed inset-0 z-[100000] flex items-center justify-center p-4" style="display:none">
            <div class="absolute inset-0 bg-charcoal/40" @click="open = false"></div>
            <div class="relative bg-[#FFFCFA] border border-beige max-w-lg w-full p-6 shadow-lg" @click.stop>
                @if($title)<h3 class="font-display text-2xl mb-4">{{ $title }}</h3>@endif
                {{ $slot }}
                <button type="button" class="btn btn-secondary mt-4" @click="open = false">Close</button>
            </div>
        </div>
    </template>
</div>
