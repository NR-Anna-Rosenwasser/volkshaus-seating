{{ $greeting }}

@foreach ($lines as $line)
{{ $line }}

@endforeach
@foreach ($seats as $seat)
- {{ $seat }}
@endforeach

{{ $buttonLabel }}: {{ $buttonUrl }}
@if ($summaryUrl)

Bestellung bei Hi.Events: {{ $summaryUrl }}
@endif

@foreach ($closing as $line)
{{ $line }}

@endforeach
{{ $personalNote }}
