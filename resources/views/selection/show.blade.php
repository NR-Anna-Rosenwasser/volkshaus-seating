@php
    $labelOf = fn ($seat) => $layout->sectionLabel($seat->section)." · Reihe {$seat->row} · Platz {$seat->number}";
    $contact = config('seating.contact_email');
@endphp

<x-layouts.public title="Platzwahl">
    <div class="space-y-5">
        <header>
            <p class="inline-block rounded-full bg-tint px-3 py-1 text-xs font-medium text-ink">Volkshaus, Theatersaal</p>
            <h1 class="mt-3 text-3xl leading-tight font-bold tracking-tight text-ink">Eure Plätze</h1>
            <p class="mt-1 text-base font-semibold text-ink">{{ $order->first_name }} {{ $order->last_name }}</p>
            @if ($mode === 'choose')
                <p class="mt-2 text-sm leading-relaxed">
                    Wählt {{ $needed === 1 ? 'einen Platz' : "{$needed} Plätze" }}. Ihr könnt eure Wahl bis {{ $deadline }} ändern.
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
                <section class="-mx-4 overflow-hidden border-y border-line sm:mx-0 sm:rounded-xl sm:border" aria-label="Saalplan">
                    <x-seat-map-frame :layout="$layout" :states="$states" />
                </section>

                <x-seat-legend selection />

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

                <button type="button" class="secondary-button w-full" x-bind:disabled="busy" x-on:click="suggest()">
                    Beste Plätze für uns finden
                </button>

                <section aria-label="Eure Auswahl" class="space-y-3">
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
                    >Alle Plätze abwählen</button>

                    <p x-cloak x-show="notice" class="notice" role="alert" x-text="notice"></p>
                    <p x-cloak x-show="hasSingleGap()" class="notice" role="alert">{{ \App\Services\SeatSelection::SINGLE_GAP_MESSAGE }}</p>
                    <p x-cloak x-show="selected.length > 1 && !together() && !hasSingleGap()" class="notice">Eure Plätze liegen nicht nebeneinander.</p>
                    <p x-cloak x-show="hasObstructed()" class="notice">Mindestens ein Platz hat eine Sichtbehinderung.</p>
                    <p x-cloak x-show="saved" class="notice" role="status">
                        Eure Plätze sind gespeichert. <a class="font-semibold text-accent underline" x-bind:href="passUrl">Sitzplatz-Pass ansehen</a>
                    </p>

                    <button type="button" class="primary-button w-full" x-bind:disabled="!canSave()" x-on:click="save()">
                        Plätze speichern
                    </button>
                </section>
            </div>
        @else
            <div class="notice" role="status">
                @switch ($mode)
                    @case('cancelled')
                        Diese Bestellung wurde storniert.
                        @break
                    @case('wheelchair')
                        Ihr habt einen Rollstuhlplatz angegeben. Wir melden uns bei euch und teilen euch die Plätze zu.
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
                            Für Änderungen schreibt uns an <a class="font-semibold text-accent underline" href="mailto:{{ $contact }}">{{ $contact }}</a>.
                        @endif
                        @break
                @endswitch
            </div>

            @if ($seats->isNotEmpty() && $mode !== 'cancelled')
                <section aria-label="Eure Plätze" class="space-y-2">
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
            Wir verwenden eure Angaben nur für die Platzwahl und löschen sie 30 Tage nach der Veranstaltung.
        </p>
    </div>
</x-layouts.public>
