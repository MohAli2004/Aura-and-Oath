@props(['title' => 'Nothing here yet', 'message' => 'Check back soon.', 'action' => null, 'actionLabel' => 'Continue'])
<div class="text-center py-16 px-6 border border-dashed border-beige">
    <h3 class="font-display text-3xl mb-2">{{ $title }}</h3>
    <p class="text-taupe mb-6">{{ $message }}</p>
    @if($action)
        <a href="{{ $action }}" class="btn btn-primary">{{ $actionLabel }}</a>
    @endif
</div>
