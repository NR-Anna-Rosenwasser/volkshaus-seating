<?php

namespace App\Console\Commands;

use App\Enums\SyncResult;
use App\Models\Order;
use App\Services\AdminAlert;
use App\Services\Hievents\HieventsClient;
use App\Services\Hievents\OrderSync;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('seating:reconcile')]
#[Description('Compare local orders with Hi.Events and fix any drift')]
class Reconcile extends Command
{
    public const LAST_RESULT_KEY = 'seating.last-reconciliation';

    public function handle(HieventsClient $client, OrderSync $sync, AdminAlert $alert): int
    {
        try {
            $counts = $this->reconcile($client, $sync);
        } catch (Throwable $exception) {
            Cache::forever(self::LAST_RESULT_KEY, ['at' => now()->toIso8601String(), 'failed' => true]);
            Log::error("Reconciliation failed: {$exception->getMessage()}");
            $alert->send('Abgleich fehlgeschlagen', 'seating:reconcile konnte nicht abgeschlossen werden.');
            $this->error('Reconciliation failed.');

            return self::FAILURE;
        }

        $summary = "created {$counts['created']}, updated {$counts['updated']}, cancelled {$counts['cancelled']}, unchanged {$counts['unchanged']}";

        Cache::forever(self::LAST_RESULT_KEY, ['at' => now()->toIso8601String(), 'failed' => false, ...$counts]);
        Log::info("Reconciliation: {$summary}");
        $this->info($summary);

        if ($counts['created'] + $counts['updated'] + $counts['cancelled'] > 0) {
            $alert->send('Abgleich hat Abweichungen korrigiert', $summary);
        }

        return self::SUCCESS;
    }

    /**
     * @return array{created: int, updated: int, cancelled: int, unchanged: int}
     */
    private function reconcile(HieventsClient $client, OrderSync $sync): array
    {
        $counts = ['created' => 0, 'updated' => 0, 'cancelled' => 0, 'unchanged' => 0];
        $known = Order::pluck('hievents_order_id')->flip();

        foreach ($client->orders() as $order) {
            // The list has no question answers, so a missed order needs its full record.
            if (! $known->has((string) $order['id']) && $order['status'] === 'COMPLETED') {
                $order = $client->order($order['id']);
            }

            $result = $sync->syncOrder($order);

            if ($result !== SyncResult::Ignored) {
                $counts[$result->value]++;
            }
        }

        return $counts;
    }
}
