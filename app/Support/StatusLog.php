<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * What has happened, for the dashboard and the overdue alert. Lives in Postgres
 * and not in the cache: losing "auto-assign has run" would trigger false alarms.
 */
class StatusLog
{
    public static function put(string $key, mixed $value): void
    {
        DB::table('status_entries')->upsert(
            ['key' => $key, 'value' => json_encode($value)],
            ['key'],
            ['value'],
        );
    }

    public static function get(string $key): mixed
    {
        $value = DB::table('status_entries')->where('key', $key)->value('value');

        return $value === null ? null : json_decode($value, true);
    }
}
