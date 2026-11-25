<?php

namespace App\Console\Commands;

use App\Enums\SyncResult;
use App\Models\Order;
use App\Services\AdminAlert;
use App\Services\Hievents\HieventsClient;
use App\Services\Hievents\OrderSync;
use App\Support\DataRetention;
use App\Support\StatusLog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * The only way orders come in: runs every two minutes and walks the whole
 * order list, because Hi.Events can't filter it by change date.
 */
#[Signature('seating:reconcile')]
#[Description('Pull all orders from Hi.Events and apply every change')]
class Reconcile extends Command
{
    public const LAST_RESULT_KEY = 'seating.last-reconciliation';

    public const HEALTH_KEY = 'seating.reconcile-health';

    /** A new order needs one extra request for its answers. Keeps a big backlog under the API limit. */
    public const MAX_DETAILS_PER_RUN = 100;

    /** Two minutes apart, so about six minutes of failures before anyone is told. */
    public const FAILURES_BEFORE_ALERT = 3;

    public function handle(HieventsClient $client, OrderSync $sync, AdminAlert $alert, DataRetention $retention): int
    {
        // It would copy every name back from Hi.Events.
        if ($retention->isOver()) {
            $this->info('Retention period is over, nothing to do.');

            return self::SUCCESS;
        }

        $health = StatusLog::get(self::HEALTH_KEY) ?? ['failures' => 0, 'alerted' => false, 'failed_orders' => []];

        try {
            $counts = $this->reconcile($client, $sync);
        } catch (Throwable $exception) {
            $health['failures']++;
            $last = StatusLog::get(self::LAST_RESULT_KEY) ?? [];
            StatusLog::put(self::LAST_RESULT_KEY, [...$last, 'at' => now()->toIso8601String(), 'failed' => true]);
            Log::error("Reconciliation failed: {$exception->getMessage()}");

            if ($health['failures'] >= self::FAILURES_BEFORE_ALERT && ! $health['alerted']) {
                $alert->send('Abgleich fehlgeschlagen', "seating:reconcile ist {$health['failures']} Mal hintereinander fehlgeschlagen. Neue Bestellungen kommen nicht an, bis Hi.Events wieder erreichbar ist.");
                $health['alerted'] = true;
            }

            StatusLog::put(self::HEALTH_KEY, $health);
            $this->error('Reconciliation failed.');

            return self::FAILURE;
        }

        if ($health['alerted']) {
            $alert->send('Abgleich läuft wieder', 'seating:reconcile hat wieder alle Bestellungen geholt.');
        }

        $failedOrders = $counts['failed_orders'];
        $newFailures = array_values(array_diff($failedOrders, $health['failed_orders']));

        if ($newFailures !== []) {
            $alert->send('Bestellung nicht übernommen', 'Diese Hi.Events-Bestellungen konnten nicht übernommen werden: '.implode(', ', $newFailures).'. Details im Log. Der Abgleich versucht es alle 2 Minuten wieder.');
        }

        StatusLog::put(self::HEALTH_KEY, ['failures' => 0, 'alerted' => false, 'failed_orders' => $failedOrders]);

        unset($counts['failed_orders']);
        $counts['failed_count'] = count($failedOrders);
        $summary = collect($counts)->map(fn (int $count, string $key): string => "{$key} {$count}")->implode(', ');

        StatusLog::put(self::LAST_RESULT_KEY, ['at' => now()->toIso8601String(), 'succeeded_at' => now()->toIso8601String(), 'failed' => false, ...$counts]);
        Log::info("Reconciliation: {$summary}");
        $this->info($summary);

        return self::SUCCESS;
    }

    /**
     * @return array{created: int, updated: int, cancelled: int, unchanged: int, deferred: int, failed_orders: list<string>}
     */
    private function reconcile(HieventsClient $client, OrderSync $sync): array
    {
        // Would fail every single order, so fail the run instead.
        if (config('seating.seated_product_ids') === []) {
            throw new RuntimeException('SEATED_PRODUCT_IDS is empty, so no attendee would get a seat.');
        }

        $counts = ['created' => 0, 'updated' => 0, 'cancelled' => 0, 'unchanged' => 0, 'deferred' => 0, 'failed_orders' => []];
        $known = Order::pluck('hievents_order_id')->flip();
        $details = 0;

        foreach ($client->orders() as $order) {
            $id = (string) $order['id'];

            // One broken order must not hold up everyone else's.
            try {
                // The list has no question answers, so a new order needs its full record.
                if (! $known->has($id) && $order['status'] === 'COMPLETED') {
                    if ($details >= self::MAX_DETAILS_PER_RUN) {
                        $counts['deferred']++;

                        continue;
                    }

                    $details++;
                    $order = $client->order($order['id']);
                }

                $result = $sync->syncOrder($order);
            } catch (Throwable $exception) {
                Log::error("Reconciliation of Hi.Events order {$id} failed: {$exception->getMessage()}");
                $counts['failed_orders'][] = $id;

                continue;
            }

            if ($result !== SyncResult::Ignored) {
                $counts[$result->value]++;
            }
        }

        return $counts;
    }
}
