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
    <body class="min-h-svh bg-blush font-sans text-body antialiased">
        <main class="mx-auto max-w-3xl px-2 py-2 sm:px-4 sm:py-6">
            <div class="rounded-2xl bg-white px-4 py-6 shadow-sm sm:px-8 sm:py-8">
                {{ $slot }}
            </div>
        </main>
    </body>
</html>
