<x-filament-panels::page>
    <div class="export-list">
        @foreach ([
            'platzliste' => ['Platzliste', 'Nach Bereich, Reihe und Platz: wer wo sitzt. Für die Platzanweisung.'],
            'namensliste' => ['Namensliste', 'Alle Tickets alphabetisch nach Nachname, auch ohne Platz. Für den Infodesk.'],
            'sperrliste' => ['Sperrliste', 'Alle gesperrten Plätze mit Grund und Notiz.'],
        ] as $type => [$title, $description])
            <x-filament::section>
                <div class="export-list__row">
                    <div>
                        <strong>{{ $title }}</strong>
                        <p>{{ $description }}</p>
                    </div>
                    <div class="export-list__actions">
                        <x-filament::button tag="a" href="{{ route('filament.admin.exports', ['type' => $type, 'format' => 'csv']) }}">CSV</x-filament::button>
                        <x-filament::button tag="a" color="gray" target="_blank" href="{{ route('filament.admin.exports', ['type' => $type, 'format' => 'html']) }}">Drucken</x-filament::button>
                    </div>
                </div>
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>
