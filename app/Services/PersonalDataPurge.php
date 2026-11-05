<?php

namespace App\Services;

use App\Models\Attendee;
use App\Models\Order;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Removes everything that points to a person and keeps the counts: how many
 * orders, tickets and seats there were, and which mails went out.
 */
class PersonalDataPurge
{
    private const PURGED_PREFIX = 'purged-';

    /**
     * @return array{orders: int, attendees: int, webhooks: int}
     */
    public function counts(): array
    {
        return [
            'orders' => Order::where('hievents_order_id', 'not like', self::PURGED_PREFIX.'%')->count(),
            'attendees' => Attendee::where('hievents_attendee_id', 'not like', self::PURGED_PREFIX.'%')->count(),
            'webhooks' => WebhookEvent::count(),
        ];
    }

    /**
     * @return array{orders: int, attendees: int, webhooks: int}
     */
    public function run(): array
    {
        return DB::transaction(function (): array {
            $counts = $this->counts();

            // Hi.Events ids go too: they would lead straight back to the person.
            DB::table('orders')
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

            DB::table('attendees')
                ->where('hievents_attendee_id', 'not like', self::PURGED_PREFIX.'%')
                ->update([
                    'hievents_attendee_id' => DB::raw("'".self::PURGED_PREFIX."' || id"),
                    'first_name' => null,
                    'last_name' => null,
                ]);

            // Webhook payloads and queued jobs hold names and addresses. Guest sessions hold IPs.
            DB::table('webhook_events')->delete();
            DB::table('jobs')->delete();
            DB::table('failed_jobs')->delete();
            DB::table('sessions')->whereNull('user_id')->delete();

            // Queued mails and Horizon's job history hold addresses too. Last, so
            // that a Redis failure rolls all of this back and the next run retries.
            Redis::connection()->flushdb();

            return $counts;
        });
    }
}
