@props(['selection' => false, 'solo' => false])

<ul class="flex flex-wrap gap-x-4 gap-y-1.5 text-sm" aria-label="Legende">
    @if ($selection)
        <li class="flex items-center gap-1.5"><span class="legend-dot legend-dot--selected"></span>{{ $solo ? 'Deine Wahl' : 'Eure Wahl' }}</li>
    @endif
    <li class="flex items-center gap-1.5"><span class="legend-dot" data-state="free"></span>Frei</li>
    <li class="flex items-center gap-1.5"><span class="legend-dot" data-state="taken"></span>Belegt</li>
    <li class="flex items-center gap-1.5"><span class="legend-dot" data-state="blocked"></span>Gesperrt</li>
    <li class="flex items-center gap-1.5"><span class="legend-dot legend-dot--obstructed" data-state="free"></span>Sichtbehinderung</li>
    <li class="flex items-center gap-1.5"><span class="legend-dot legend-dot--pillar"></span>Säule</li>
</ul>
