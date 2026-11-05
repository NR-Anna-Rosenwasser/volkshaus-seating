<!DOCTYPE html>
<html lang="de-CH">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <title>{{ $title }}</title>
        <style>
            body { margin: 1.5rem; color: #181818; font-family: Helvetica, Arial, sans-serif; font-size: 12px; }
            h1 { margin: 0 0 0.25rem; font-size: 20px; }
            p { margin: 0 0 1rem; color: #6b6b6b; }
            table { width: 100%; border-collapse: collapse; }
            th, td { padding: 4px 6px; border-bottom: 1px solid #cfcfcf; text-align: left; }
            thead { display: table-header-group; }
            tr { break-inside: avoid; }
            button { margin-bottom: 1rem; padding: 8px 14px; font: inherit; cursor: pointer; }
            @media print { body { margin: 0; } button { display: none; } }
        </style>
    </head>
    <body>
        <h1>{{ $title }}</h1>
        <p>{{ count($rows) }} Einträge, Stand {{ now()->format('d.m.Y, H:i') }} Uhr</p>
        <button type="button" onclick="window.print()">Drucken</button>
        <table>
            <thead>
                <tr>
                    @foreach ($headers as $header)
                        <th>{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        @foreach ($row as $cell)
                            <td>{{ $cell }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </body>
</html>
