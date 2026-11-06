<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\SeatExports;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function __invoke(Event $event, string $type, string $format, SeatExports $exports): Response
    {
        abort_unless(in_array($type, SeatExports::TYPES, true), 404);

        $export = $exports->make($event, $type);

        $response = $format === 'csv' ? $this->csv("{$event->slug}-{$type}", $export) : response()->view('admin.export-print', $export);

        // Lists of names don't belong in any cache.
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    /**
     * @param  array{title: string, headers: list<string>, rows: list<list<string>>}  $export
     */
    private function csv(string $name, array $export): StreamedResponse
    {
        return response()->streamDownload(function () use ($export): void {
            $file = fopen('php://output', 'w');

            // Excel reads UTF-8 only with this mark, and expects semicolons in Switzerland.
            fwrite($file, "\xEF\xBB\xBF");
            fputcsv($file, $export['headers'], ';');

            foreach ($export['rows'] as $row) {
                fputcsv($file, array_map($this->disarm(...), $row), ';');
            }

            fclose($file);
        }, "{$name}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Names come from buyers. A leading = + - or @ would run as a spreadsheet formula.
     */
    private function disarm(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'{$value}" : $value;
    }
}
