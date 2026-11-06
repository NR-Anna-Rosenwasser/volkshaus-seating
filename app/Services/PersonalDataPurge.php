<?php

namespace App\Services;

use App\Models\Attendee;
use App\Models\Event;
use App\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Removes everything that points to a person and keeps the counts: how many
 * orders, tickets and seats there were, and which mails went out.
 *
 * Other events are live while this runs, so it never touches the queue. Queued
 * mails carry addresses, which is why they are dropped when they come up after
 * a purge, and why job history is pruned by age in housekeeping().
 */
class PersonalDataPurge
{
    private const PURGED_PREFIX = 'purged-';

    /** Horizon trims failed jobs after the same time. See config/horizon.php. */
    private const JOB_HISTORY_DAYS = 7;

    /**
     * @return array{orders: int, attendees: int, webhooks: int}
     */
    public function counts(Event $event): array
    {
        return [
            'orders' => $event->orders()->where('hievents_order_id', 'not like', self::PURGED_PREFIX.'%')->count(),
            'attendees' => $this->attendees($event)->count(),
            'webhooks' => $this->webhooks($event)->count(),
        ];
    }

    /**
     * @return array{orders: int, attendees: int, webhooks: int}
     */
    public function run(Event $event): array
    {
        return DB::transaction(function () use ($event): array {
            $counts = $this->counts($event);

            // Hi.Events ids go too: they would lead straight back to the person.
            DB::table('orders')
                ->where('event_id', $event->id)
                ->where('hievents_order_id', 'not like', self::PURGED_PREFIX.'%')
                ->update([
                    'hievents_order_id' => DB::raw("'".self::PURGED_PREFIX."' || id"),
                    'hievents_public_id' => null,
                    'short_id' => null,
                    'email' => null,
                    'first_name' => null,
                    'last_name' => null,
                    'locale' => null,
                    // A wish for a wheelchair place is sensitive on its own.
                    'needs_wheelchair' => false,
                    'wheelchair_notified_at' => null,
                    'token_hash' => DB::raw("encode(sha256(gen_random_uuid()::text::bytea), 'hex')"),
                ]);

            $this->attendees($event)->update([
                'hievents_attendee_id' => DB::raw("'".self::PURGED_PREFIX."' || id"),
                'first_name' => null,
                'last_name' => null,
            ]);

            // Webhook payloads hold names and addresses.
            $this->webhooks($event)->delete();

            $event->update(['purged_at' => now()]);

            return $counts;
        });
    }

    /**
     * Personal data that can't be traced to one event, so it goes by age.
     * Failed jobs hold addresses, guest sessions hold IPs.
     */
    public function housekeeping(): void
    {
        $cutoff = now()->subDays(self::JOB_HISTORY_DAYS);

        DB::table('failed_jobs')->where('failed_at', '<', $cutoff)->delete();
        DB::table('sessions')->whereNull('user_id')->where('last_activity', '<', $cutoff->getTimestamp())->delete();
    }

    /**
     * @return Builder<Attendee>
     */
    private function attendees(Event $event): Builder
    {
        return Attendee::query()
            ->where('hievents_attendee_id', 'not like', self::PURGED_PREFIX.'%')
            ->whereIn('order_id', $event->orders()->select('id'));
    }

    /**
     * @return Builder<WebhookEvent>
     */
    private function webhooks(Event $event): Builder
    {
        return WebhookEvent::query()->whereRaw("payload->'payload'->>'event_id' = ?", [$event->hievents_event_id]);
    }
}
