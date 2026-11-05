@php
    $swiss = fn (int $number): string => number_format($number, 0, '.', "'");
    $sections = $layout->sections();
@endphp

<x-layouts.public title="Saalplan">
    <div
        x-data="seatMap({
            url: @js(route('api.plan')),
            refreshMs: {{ \App\Services\SeatPlan::CACHE_SECONDS * 1000 }},
            sections: @js($plan['sections']),
            total: @js($plan['total']),
        })"
        class="space-y-5"
    >
        <header>
            <p class="inline-block rounded-full bg-tint px-3 py-1 text-xs font-medium text-ink">Volkshaus, Theatersaal</p>
            <h1 class="mt-3 text-3xl leading-tight font-bold tracking-tight text-ink">Saalplan</h1>
            <p class="mt-1 text-base leading-snug font-semibold text-ink tabular-nums">
                Noch
                <span x-text="swiss(total.free)">{{ $swiss($plan['total']['free']) }}</span>
                von
                <span x-text="swiss(total.sellable)">{{ $swiss($plan['total']['sellable']) }}</span>
                Plätzen frei
            </p>
        </header>

        <section
            class="-mx-4 overflow-hidden border-y border-line sm:mx-0 sm:rounded-xl sm:border"
            aria-label="Saalplan"
        >
            {{-- Controls sit in their own strips so they never cover a seat. --}}
            <div x-cloak class="flex items-center gap-1 border-b border-line p-2">
                <button type="button" class="map-button px-3" x-show="zoomed" x-on:click="showOverview()">Übersicht</button>
                <button type="button" class="map-button ml-auto w-11" aria-label="Verkleinern" x-on:click="zoomBy(1 / 1.5)">−</button>
                <button type="button" class="map-button w-11" aria-label="Vergrössern" x-on:click="zoomBy(1.5)">+</button>
            </div>

            <div x-ref="viewport" class="seat-map-viewport">
                <x-seat-map :layout="$layout" :states="$plan['seats']" />
            </div>

            <p
                role="status"
                class="flex min-h-14 items-center border-t border-line px-3 py-2 text-sm leading-snug"
                x-bind:class="seat ? 'font-semibold text-ink' : 'text-muted'"
                x-text="seat ?? (zoomed ? 'Tippt auf einen Platz für Details.' : 'Tippt auf einen Bereich, um ihn zu vergrössern.')"
            >Tippt auf einen Bereich, um ihn zu vergrössern.</p>
        </section>

        <nav aria-label="Bereiche">
            <ul class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                @foreach ($sections as $section)
                    <li>
                        <button
                            type="button"
                            class="section-button"
                            x-bind:aria-current="section === @js($section['id']) ? 'true' : null"
                            x-on:click="showSection(@js($section['id']))"
                        >
                            <span class="block text-sm font-semibold text-ink">{{ $section['label'] }}</span>
                            <span class="block text-sm text-muted tabular-nums">
                                <span x-text="swiss(sections[@js($section['id'])]?.free ?? 0)">{{ $swiss($plan['sections'][$section['id']]['free'] ?? 0) }}</span>
                                frei
                            </span>
                        </button>
                    </li>
                @endforeach
            </ul>
        </nav>

        <aside class="space-y-4">
            <ul class="flex flex-wrap gap-x-4 gap-y-1.5 text-sm" aria-label="Legende">
                <li class="flex items-center gap-1.5"><span class="legend-dot" data-state="free"></span>Frei</li>
                <li class="flex items-center gap-1.5"><span class="legend-dot" data-state="taken"></span>Belegt</li>
                <li class="flex items-center gap-1.5"><span class="legend-dot" data-state="blocked"></span>Gesperrt</li>
                <li class="flex items-center gap-1.5"><span class="legend-dot legend-dot--obstructed" data-state="free"></span>Sichtbehinderung</li>
                <li class="flex items-center gap-1.5"><span class="legend-dot legend-dot--pillar"></span>Säule</li>
            </ul>

            <p class="text-sm leading-relaxed text-muted">
                Die Platzwahl erfolgt nach dem Ticketkauf. Freie Plätze werden nicht reserviert.
            </p>
        </aside>
    </div>
</x-layouts.public>
