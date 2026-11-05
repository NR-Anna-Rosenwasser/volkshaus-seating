<x-filament-panels::page>
    <div class="info-desk">
        <input
            type="search"
            class="info-desk__search"
            placeholder="Name oder Bestellnummer"
            aria-label="Name oder Bestellnummer"
            autocomplete="off"
            autofocus
            wire:model.live.debounce.250ms="search"
        >

        @foreach ($hits as $hit)
            <article class="info-desk__hit" wire:key="hit-{{ $hit['id'] }}">
                <p class="info-desk__name">{{ $hit['name'] }}</p>

                @if ($hit['cancelled'])
                    <p class="info-desk__warning">Diese Bestellung wurde storniert.</p>
                @endif

                @foreach ($hit['seats'] as $seat)
                    <p class="info-desk__seat">{{ $seat }}</p>
                @endforeach

                @if ($hit['missing'] > 0)
                    <p class="info-desk__warning">{{ $hit['missing'] }} {{ $hit['missing'] === 1 ? 'Ticket' : 'Tickets' }} ohne Platz</p>
                @endif

                @if ($hit['wheelchair'])
                    <p class="info-desk__warning">Rollstuhlplatz</p>
                @endif

                <p class="info-desk__meta">{{ $hit['number'] }} · {{ $hit['people'] }}</p>
            </article>
        @endforeach

        @if ($searched && $hits === [])
            <p class="info-desk__meta">Niemand gefunden.</p>
        @endif
    </div>
</x-filament-panels::page>
