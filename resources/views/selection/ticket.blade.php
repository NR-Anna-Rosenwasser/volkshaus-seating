<x-layouts.public title="Sitzplatz-Pass">
    <div class="space-y-5">
        <header>
            <p class="inline-block rounded-full bg-tint px-3 py-1 text-xs font-medium text-ink">Volkshaus, Theatersaal</p>
            <h1 class="mt-3 text-3xl leading-tight font-bold tracking-tight text-ink">{{ config('seating.event_name') ?: 'Sitzplatz-Pass' }}</h1>
            @if (config('seating.event_date'))
                <p class="mt-2 text-sm leading-relaxed">{{ $eventDate }}</p>
            @endif
            @if (config('seating.doors_at'))
                <p class="text-sm leading-relaxed">Türöffnung: {{ $doorsAt }}</p>
            @endif
        </header>

        @if ($seats->isEmpty())
            <p class="notice">Ihr habt noch keine Plätze.</p>
        @else
            <ul class="space-y-2">
                @foreach ($seats as $seat)
                    <li class="text-2xl leading-snug font-bold text-ink">
                        {{ $layout->sectionLabel($seat->section) }} · Reihe {{ $seat->row }} · Platz {{ $seat->number }}
                    </li>
                @endforeach
            </ul>

            <div class="mx-auto max-w-sm">
                <x-seat-map class="seat-map--pass" :layout="$layout" :states="[]" :highlight="$seats->pluck('id')->all()" />
            </div>
        @endif

        <p class="notice">Einlass nur mit dem Ticket von Hi.Events. Diese Seite zeigt nur euren Sitzplatz.</p>

        <button type="button" class="secondary-button no-print w-full" onclick="window.print()">Drucken</button>
    </div>
</x-layouts.public>
