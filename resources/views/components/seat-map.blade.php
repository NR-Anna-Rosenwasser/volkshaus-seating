@props(['layout', 'states', 'highlight' => []])

@php
    $viewBox = $layout->viewBox();
    $stage = $layout->stage();
    $radius = $layout->seatRadius();
    $sections = $layout->sections();
@endphp

{{-- x-ignore keeps Alpine from walking thousands of nodes; seat-map.js drives the SVG directly. --}}
<svg
    {{ $attributes->class('seat-map') }}
    x-ignore
    xmlns="http://www.w3.org/2000/svg"
    viewBox="{{ $viewBox['x'] }} {{ $viewBox['y'] }} {{ $viewBox['width'] }} {{ $viewBox['height'] }}"
    role="img"
    aria-label="Saalplan mit Bühne und Sitzbereichen"
>
    <g class="seat-map__stage">
        <rect x="{{ $stage['x0'] }}" y="{{ $stage['y0'] }}" width="{{ $stage['x1'] - $stage['x0'] }}" height="{{ $stage['y1'] - $stage['y0'] }}" />
        <text x="{{ ($stage['x0'] + $stage['x1']) / 2 }}" y="{{ ($stage['y0'] + $stage['y1']) / 2 }}">{{ $stage['label'] }}</text>
    </g>

    @foreach ($sections as $section)
        <g class="seat-map__section" data-section-seats="{{ $section['id'] }}" data-label="{{ $section['label'] }}">
            @foreach ($layout->seats($section['id']) as $seat)
                <g
                    @class([
                        'seat',
                        'seat--obstructed' => in_array('obstructed', $seat['flags'], true),
                        'is-selected' => in_array($seat['id'], $highlight, true),
                    ])
                    data-seat="{{ $seat['id'] }}"
                    data-row="{{ $seat['row'] }}"
                    data-number="{{ $seat['number'] }}"
                    data-segment="{{ $seat['segment'] }}"
                    data-state="{{ $states[$seat['id']] ?? 'blocked' }}"
                ><circle cx="{{ $seat['x'] }}" cy="{{ $seat['y'] }}" r="{{ $radius }}" /><text x="{{ $seat['x'] }}" y="{{ $seat['y'] }}">{{ $seat['number'] }}</text></g>
            @endforeach
        </g>
    @endforeach

    <g class="seat-map__pillars">
        @foreach ($layout->pillars() as $pillar)
            <rect x="{{ $pillar['x'] - 3.6 }}" y="{{ $pillar['y'] - 3.6 }}" width="7.2" height="7.2" />
        @endforeach
    </g>

    <g class="seat-map__rows">
        @foreach ($sections as $section)
            @foreach ($section['rowLabels'] as $label)
                <text x="{{ $label['x'] }}" y="{{ $label['y'] }}">{{ $label['text'] }}</text>
            @endforeach
        @endforeach
    </g>

    <g class="seat-map__labels">
        @foreach ($sections as $section)
            <text x="{{ $section['labelX'] }}" y="{{ $section['labelY'] }}">{{ $section['label'] }}</text>
        @endforeach
    </g>

    {{-- Tap targets for the overview, on top of everything else. --}}
    @foreach ($sections as $section)
        <rect
            class="seat-map__hit"
            data-section="{{ $section['id'] }}"
            x="{{ $section['box']['x'] }}"
            y="{{ $section['box']['y'] }}"
            width="{{ $section['box']['width'] }}"
            height="{{ $section['box']['height'] }}"
        />
    @endforeach
</svg>
