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
{{ $personalNote }}
