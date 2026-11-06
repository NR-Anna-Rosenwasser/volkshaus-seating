<x-layouts.public title="Sitzplatz-Pass" :event="$event">
    <div class="space-y-5">
        <header>
            <x-venue-chip :event="$event" />
            <h1 class="mt-3 text-3xl leading-tight font-bold tracking-tight text-ink">{{ $event->name }}</h1>
            @if ($event->starts_at)
                <p class="mt-2 text-sm leading-relaxed">{{ $eventDate }}</p>
            @endif
            @if ($event->doors_at)
                <p class="text-sm leading-relaxed">Türöffnung: {{ $doorsAt }}</p>
            @endif
        </header>

        @if ($seats->isEmpty())
            <p class="notice">{{ $order->say('Du hast noch keinen Platz.', 'Ihr habt noch keine Plätze.') }}</p>
        @else
            <ul class="space-y-2">
                @foreach ($seats as $seat)
                    <li class="text-2xl leading-snug font-bold text-ink">
                        {{ $layout->seatLabel($seat) }}
                    </li>
                @endforeach
            </ul>

            <div class="mx-auto max-w-sm">
                <x-seat-map class="seat-map--pass" :layout="$layout" :states="[]" :highlight="$seats->pluck('code')->all()" />
            </div>
        @endif

        <p class="notice">Einlass nur mit dem Ticket von Hi.Events. Diese Seite zeigt nur {{ $order->say('deinen', 'euren') }} Sitzplatz.</p>

        <button type="button" class="secondary-button no-print w-full" onclick="window.print()">Drucken</button>
    </div>
</x-layouts.public>
