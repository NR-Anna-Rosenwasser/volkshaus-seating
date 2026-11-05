@props(['layout', 'states'])

{{-- Controls sit in their own strips so they never cover a seat. --}}
<div x-cloak class="flex items-center gap-1 border-b border-line p-2">
    <button type="button" class="map-button px-3" x-show="zoomed" x-on:click="showOverview()">Übersicht</button>
    <button type="button" class="map-button ml-auto w-11" aria-label="Verkleinern" x-on:click="zoomBy(1 / 1.5)">−</button>
    <button type="button" class="map-button w-11" aria-label="Vergrössern" x-on:click="zoomBy(1.5)">+</button>
</div>

<div x-ref="viewport" class="seat-map-viewport">
    <x-seat-map :layout="$layout" :states="$states" />
</div>

<p
    role="status"
    class="flex min-h-14 items-center border-t border-line px-3 py-2 text-sm leading-snug"
    x-bind:class="seat ? 'font-semibold text-ink' : 'text-muted'"
    x-text="seat ?? hint()"
>Tippt auf einen Bereich, um ihn zu vergrössern.</p>
