{{ $greeting }}

@foreach ($lines as $line)
{{ $line }}

@endforeach
@foreach ($seats as $seat)
- {{ $seat }}
@endforeach

{{ $buttonLabel }}: {{ $buttonUrl }}

@foreach ($closing as $line)
{{ $line }}

@endforeach
Dieser Link ist persönlich. Bitte leitet ihn nicht weiter.
