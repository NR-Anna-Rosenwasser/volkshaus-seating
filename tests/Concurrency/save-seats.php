<?php

// Saves seats for one order in its own process, starting at an agreed moment.

use App\Exceptions\SeatConflict;
use App\Models\Order;
use App\Services\SeatSelection;
use Illuminate\Contracts\Console\Kernel;

[, $orderId, $startAt, $seats] = $argv;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$order = Order::findOrFail($orderId);

time_sleep_until((float) $startAt);

try {
    $app->make(SeatSelection::class)->save($order, explode(',', $seats));
    echo 'saved';
} catch (SeatConflict) {
    echo 'conflict';
}
