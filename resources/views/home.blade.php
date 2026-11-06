<x-layouts.public title="Sitzplatzwahl">
    <div class="space-y-5">
        <header>
            <h1 class="text-3xl leading-tight font-bold tracking-tight text-ink">Sitzplatzwahl</h1>
        </header>

        @if ($events->isEmpty())
            <p class="leading-relaxed">Zurzeit gibt es hier keine Veranstaltungen.</p>
        @else
            <ul class="space-y-2">
                @foreach ($events as $event)
                    <li>
                        <a class="section-button block" href="{{ route('plan', $event) }}">
                            <span class="block text-sm font-semibold text-ink">{{ $event->name }}</span>
                            @if ($event->starts_at)
                                <span class="block text-sm text-muted">{{ \App\Support\SelectionWindow::format($event->starts_at) }}</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts.public>
