<x-layouts.public title="Sitzplatz-Pass">
    <div class="space-y-5">
        @if ($justSaved && $seats->isNotEmpty())
            <div class="notice notice--success no-print" role="status">
                <p class="font-semibold">{{ $order->say('Dein Platz ist gespeichert.', 'Eure Plätze sind gespeichert.') }}</p>
                @if ($canChange)
                    <p>{{ $order->say("Du kannst ihn bis {$deadline} ändern.", "Ihr könnt sie bis {$deadline} ändern.") }}</p>
                @endif
            </div>
        @endif

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
                <x-seat-map class="seat-map--pass" :layout="$layout" :states="[]" :highlight="$seats->pluck('id')->all()" />
            </div>
        @endif

        <p class="notice">{{ $order->say('Einlass nur mit dem Ticket von Hi.Events. Diese Seite zeigt nur deinen Sitzplatz.', 'Einlass nur mit den Tickets von Hi.Events. Diese Seite zeigt nur eure Sitzplätze.') }}</p>

        <div class="no-print space-y-2">
            @if ($canChange)
                <a class="secondary-button w-full" href="{{ route('selection.show', request()->route()->originalParameter('token')) }}">{{ $order->say('Platz ändern', 'Plätze ändern') }}</a>
            @endif
            @if ($summaryUrl)
                <a class="secondary-button w-full" href="{{ $summaryUrl }}" target="_blank" rel="noopener noreferrer">
                    <span class="inline-flex items-center gap-2">
                        Bestellung bei Hi.Events ansehen
                        <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>
                    </span>
                </a>
            @endif
            <button type="button" class="secondary-button w-full" onclick="window.print()">Drucken</button>
        </div>
    </div>
</x-layouts.public>
