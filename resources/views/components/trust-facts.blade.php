@php
    $facts = \App\Support\TrustMessaging::homeFacts();
@endphp
@if($facts !== [])
    <section class="border-t border-beige bg-[#F3EEE7] py-12 sm:py-14" aria-label="How we work">
        <div class="max-w-7xl mx-auto px-4 sm:px-6">
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-8">
                @foreach($facts as $fact)
                    <div>
                        <h2 class="font-display text-xl mb-2">{{ $fact['title'] }}</h2>
                        <p class="text-sm text-taupe leading-relaxed">{{ $fact['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif
