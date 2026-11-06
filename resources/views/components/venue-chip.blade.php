@props(['event'])

@if ($event->venue_name)
    <p class="inline-block rounded-full bg-tint px-3 py-1 text-xs font-medium text-ink">{{ $event->venue_name }}</p>
@endif
