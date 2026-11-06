@php
    $swiss = fn (int $number): string => number_format($number, 0, '.', "'");
    $sections = $layout->sections();
@endphp

<x-layouts.public title="Saalplan" :event="$event">
    <div
        x-data="seatMap({
            url: @js(route('api.plan', $event)),
            refreshMs: {{ \App\Services\SeatPlan::CACHE_SECONDS * 1000 }},
            sections: @js($plan['sections']),
            total: @js($plan['total']),
        })"
        class="space-y-5"
    >
        <header>
            <x-venue-chip :event="$event" />
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
            <x-seat-map-frame :layout="$layout" :states="$plan['seats']" />
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
            <x-seat-legend />

            <p class="text-sm leading-relaxed text-muted">
                Die Platzwahl erfolgt nach dem Ticketkauf. Freie Plätze werden nicht reserviert.
            </p>
        </aside>
    </div>
</x-layouts.public>
