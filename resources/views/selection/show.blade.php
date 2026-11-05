@php
    $labelOf = fn ($seat) => $layout->sectionLabel($seat->section)." · Reihe {$seat->row} · Platz {$seat->number}";
    $contact = config('seating.contact_email');
    $say = fn (string $solo, string $group): string => $needed === 1 ? $solo : $group;
@endphp

<x-layouts.public title="Platzwahl">
    <div class="space-y-5">
        <header>
            <p class="inline-block rounded-full bg-tint px-3 py-1 text-xs font-medium text-ink">Volkshaus, Theatersaal</p>
            <h1 class="mt-3 text-3xl leading-tight font-bold tracking-tight text-ink">{{ $say('Dein Platz', 'Eure Plätze') }}</h1>
            <p class="mt-1 text-base font-semibold text-ink">{{ $order->first_name }} {{ $order->last_name }}</p>
            @if ($mode === 'choose')
                <p class="mt-2 text-sm leading-relaxed">
                    {{ $say("Wähle einen Platz. Du kannst deine Wahl bis {$deadline} ändern.", "Wählt {$needed} Plätze. Ihr könnt eure Wahl bis {$deadline} ändern.") }}
                </p>
            @endif
        </header>

        @if ($mode === 'choose')
            <div
                x-data="seatSelection({
                    needed: {{ $needed }},
                    initial: @js($seats->pluck('id')),
                    url: @js(route('selection.availability', request()->route()->originalParameter('token'))),
                    suggestUrl: @js(route('selection.suggestion', request()->route()->originalParameter('token'))),
                    saveUrl: @js(route('selection.save', request()->route()->originalParameter('token'))),
                    passUrl: @js(route('selection.ticket', request()->route()->originalParameter('token'))),
                    csrf: @js(csrf_token()),
                    sections: {},
                    total: {},
                })"
                class="space-y-5"
            >
                <button type="button" class="secondary-button w-full" x-bind:disabled="busy" x-on:click="suggest()">
                    {{ $say('Besten Platz für mich finden', 'Beste Plätze für uns finden') }}
                </button>
                <section class="-mx-4 overflow-hidden border-y border-line sm:mx-0 sm:rounded-xl sm:border" aria-label="Saalplan">
                    <x-seat-map-frame :layout="$layout" :states="$states" />
                </section>

                <x-seat-legend selection :solo="$needed === 1" />

                <nav aria-label="Bereiche">
                    <ul class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        @foreach ($layout->sections() as $section)
                            <li>
                                <button
                                    type="button"
                                    class="section-button"
                                    x-bind:aria-current="section === @js($section['id']) ? 'true' : null"
                                    x-on:click="showSection(@js($section['id']))"
                                >
                                    <span class="block text-sm font-semibold text-ink">{{ $section['label'] }}</span>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </nav>

                <section aria-label="{{ $say('Deine Auswahl', 'Eure Auswahl') }}" class="space-y-3">
                    <h2 class="text-base font-semibold text-ink">
                        <span x-text="selected.length">{{ $seats->count() }}</span> von {{ $needed }} gewählt
                    </h2>

                    <ul class="space-y-1 text-sm text-ink">
                        <template x-for="seat in selectedSeats()" x-bind:key="seat.id">
                            <li class="flex items-center justify-between gap-3">
                                <span x-text="seat.label"></span>
                                <button
                                    type="button"
                                    class="remove-button"
                                    x-bind:aria-label="`${seat.label} abwählen`"
                                    x-on:click="remove(seat.id)"
                                >×</button>
                            </li>
                        </template>
                    </ul>

                    <button
                        x-cloak
                        x-show="selected.length > 0"
                        type="button"
                        class="text-sm font-semibold text-accent underline"
                        x-on:click="clearAll()"
                    >{{ $say('Platz abwählen', 'Alle Plätze abwählen') }}</button>

                    <p x-cloak x-show="notice" class="notice" role="alert" x-text="notice"></p>
                    @if ($needed > 1)
                        <p x-cloak x-show="hasSingleGap()" class="notice" role="alert">{{ \App\Services\SeatSelection::SINGLE_GAP_MESSAGE }}</p>
                        <p x-cloak x-show="selected.length > 1 && !together() && !hasSingleGap()" class="notice">Eure Plätze liegen nicht nebeneinander.</p>
                    @endif
                    <p x-cloak x-show="hasObstructed()" class="notice">{{ $say('Dieser Platz hat eine Sichtbehinderung.', 'Mindestens ein Platz hat eine Sichtbehinderung.') }}</p>
                    <p x-cloak x-show="saved" class="notice" role="status">
                        {{ $say('Dein Platz ist gespeichert.', 'Eure Plätze sind gespeichert.') }} <a class="font-semibold text-accent underline" x-bind:href="passUrl">Sitzplatz-Pass ansehen</a>
                    </p>

                    <button type="button" class="primary-button w-full" x-bind:disabled="!canSave()" x-on:click="save()">
                        {{ $say('Platz speichern', 'Plätze speichern') }}
                    </button>
                </section>

                @if ($needed > 1)
                    <details class="help-toggle">
                        <summary>Findet ihr keine Plätze für eure Gruppe?</summary>
                        <div class="help-toggle__body">
                            <p>Wenn sich der Saal füllt, gibt es nicht mehr überall genug freie Plätze nebeneinander. Das passiert vor allem grösseren Gruppen.</p>
                            <ul>
                                <li>«Beste Plätze für uns finden» sucht die beste Aufteilung, die noch möglich ist.</li>
                                <li>Ihr könnt euch auch aufteilen, zum Beispiel auf zwei Reihen hintereinander.</li>
                            </ul>
                            <p>
                                Braucht ihr Hilfe?
                                @if ($contact)
                                    Schreibt uns an <a class="font-semibold text-accent underline" href="mailto:{{ $contact }}">{{ $contact }}</a>, dann suchen wir gemeinsam gute Plätze für euch.
                                @else
                                    Meldet euch bei uns, dann suchen wir gemeinsam gute Plätze für euch.
                                @endif
                            </p>
                        </div>
                    </details>
                @endif
            </div>
        @else
            <div class="notice" role="status">
                @switch ($mode)
                    @case('cancelled')
                        Diese Bestellung wurde storniert.
                        @break
                    @case('wheelchair')
                        {{ $say('Du hast einen Rollstuhlplatz angegeben. Wir melden uns bei dir und teilen dir den Platz zu.', 'Ihr habt einen Rollstuhlplatz angegeben. Wir melden uns bei euch und teilen euch die Plätze zu.') }}
                        @break
                    @case('none')
                        Für diese Bestellung braucht ihr keine Sitzplätze.
                        @break
                    @case('not-open')
                        Die Platzwahl ist noch nicht geöffnet. Sie startet am {{ $opensAt }}.
                        @break
                    @case('closed')
                        Die Platzwahl ist beendet.
                        @if ($contact)
                            {{ $say('Für Änderungen schreib uns an', 'Für Änderungen schreibt uns an') }} <a class="font-semibold text-accent underline" href="mailto:{{ $contact }}">{{ $contact }}</a>.
                        @endif
                        @break
                @endswitch
            </div>

            @if ($seats->isNotEmpty() && $mode !== 'cancelled')
                <section aria-label="{{ $say('Dein Platz', 'Eure Plätze') }}" class="space-y-2">
                    <ul class="space-y-1 text-base font-semibold text-ink">
                        @foreach ($seats as $seat)
                            <li>{{ $labelOf($seat) }}</li>
                        @endforeach
                    </ul>
                    <a class="font-semibold text-accent underline" href="{{ route('selection.ticket', request()->route()->originalParameter('token')) }}">Sitzplatz-Pass ansehen</a>
                </section>
            @endif
        @endif

        <p class="text-xs leading-relaxed text-muted">
            Wir verwenden {{ $say('deine', 'eure') }} Angaben nur für die Platzwahl und löschen sie 30 Tage nach der Veranstaltung.
        </p>
    </div>
</x-layouts.public>
