@extends('layouts.admin')
@section('heading', 'Delivery regions')
@section('content')
<p class="text-sm text-taupe max-w-3xl mb-4">Untick the regions you want to hide from checkout, then click <strong>Save</strong>. Keep at least one visible.</p>
<x-admin.bulk-form
    :action="route('admin.delivery-regions.bulk-destroy')"
    :ids="$regions->pluck('id')"
    confirm="Delete the selected delivery regions? This cannot be undone."
>
    <x-slot:actions>
        <a href="{{ route('admin.delivery-regions.create') }}" class="btn btn-primary">Add region</a>
    </x-slot:actions>

    <form method="POST" action="{{ route('admin.delivery-regions.visibility') }}">
        @csrf
        @method('PATCH')
        <div class="mb-4">
            <button class="btn btn-primary" type="submit">Save</button>
        </div>
        <div class="border border-beige bg-[#FFFCFA]">
        @forelse($regions as $region)
            <div class="flex items-center justify-between gap-3 border-b border-beige p-3 text-sm">
                <div class="flex items-center gap-3 min-w-0">
                    <x-admin.bulk-checkbox :id="$region->id" />
                    <div class="min-w-0">
                        <div>
                            <strong>{{ $region->name }}</strong>
                            @if($region->code)
                                <span class="text-taupe">· {{ $region->code }}</span>
                            @endif
                            <span class="text-taupe">· {{ money($region->fee) }}</span>
                            @unless($region->is_active)
                                <span class="text-taupe">· Hidden from customers</span>
                            @else
                                <span class="text-taupe">· Visible at checkout</span>
                            @endunless
                        </div>
                        @if($region->description)
                            <div class="truncate text-xs text-taupe mt-0.5">{{ $region->description }}</div>
                        @endif
                    </div>
                </div>
                <div class="flex items-center gap-3 shrink-0">
                    <input type="hidden" name="is_active[{{ $region->id }}]" value="0">
                    <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
                        <input
                            type="checkbox"
                            name="is_active[{{ $region->id }}]"
                            value="1"
                            class="rounded border-beige text-charcoal focus:ring-gold"
                            @checked($region->is_active)
                        >
                        <span>{{ $region->is_active ? 'Visible' : 'Hidden' }}</span>
                    </label>
                    <a class="underline" href="{{ route('admin.delivery-regions.edit', $region) }}">Edit</a>
                </div>
            </div>
        @empty
            <div class="p-4 text-sm text-taupe">No delivery regions yet.</div>
        @endforelse
        </div>
    </form>
</x-admin.bulk-form>
<x-admin.pagination :paginator="$regions" noun="region" />
@endsection
