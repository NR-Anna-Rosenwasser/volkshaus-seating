<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\WebhookEvent;
use App\Services\AdminAlert;
use App\Services\Hievents\OrderSync;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessWebhookEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public int $webhookEventId) {}

    public function handle(OrderSync $sync): void
    {
        $event = WebhookEvent::findOrFail($this->webhookEventId);
        $payload = $event->payload['payload'] ?? [];
        $target = Event::where('hievents_event_id', (string) ($payload['event_id'] ?? ''))->first();

        // Everything else, including attendee.created, is covered by the order events.
        if ($target !== null) {
            match ($event->event_type) {
                'order.created', 'order.updated', 'order.marked_as_paid', 'order.refunded', 'order.cancelled' => $sync->syncOrder($target, $payload),
                'attendee.updated', 'attendee.cancelled' => $sync->syncAttendee($target, $payload),
                default => null,
            };
        }

        $event->update(['processed_at' => now(), 'error' => null]);
    }

    public function failed(Throwable $exception): void
    {
        WebhookEvent::whereKey($this->webhookEventId)->update(['error' => $exception->getMessage()]);

        app(AdminAlert::class)->send('Webhook fehlgeschlagen', "webhook_events #{$this->webhookEventId} konnte nicht verarbeitet werden.");
    }
}
