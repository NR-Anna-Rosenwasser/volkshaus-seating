<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWebhookEvent;
use App\Models\WebhookEvent;
use App\Support\DataRetention;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class HieventsWebhookController extends Controller
{
    public function __invoke(Request $request, DataRetention $retention): Response
    {
        $body = $request->getContent();

        abort_unless($this->hasValidSignature($request, $body), 401);

        $data = json_decode($body, true);

        abort_unless(is_array($data), 400);

        // Answered, so Hi.Events stops retrying, but nothing personal may come back in.
        if ($retention->isOver()) {
            return response()->noContent(200);
        }

        $event = WebhookEvent::firstOrCreate(['payload_hash' => hash('sha256', $body)], [
            'event_type' => $data['event_type'] ?? null,
            'payload' => $data,
            'received_at' => now(),
        ]);

        if ($event->wasRecentlyCreated) {
            ProcessWebhookEvent::dispatch($event->id);
        }

        return response()->noContent(200);
    }

    private function hasValidSignature(Request $request, string $body): bool
    {
        $secret = (string) config('seating.hievents.webhook_secret');
        $signature = (string) $request->header('Signature');

        return $secret !== '' && hash_equals(hash_hmac('sha256', $body, $secret), $signature);
    }
}
