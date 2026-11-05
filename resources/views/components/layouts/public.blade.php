@props(['title'])

<!DOCTYPE html>
<html lang="de-CH">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <title>{{ $title }} · Volkshaus Zürich</title>
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-svh bg-paper font-sans text-ink antialiased">
        <main class="mx-auto max-w-6xl px-4 pt-6 pb-10 sm:px-6 sm:pt-10">
            {{ $slot }}
        </main>
    </body>
</html>
