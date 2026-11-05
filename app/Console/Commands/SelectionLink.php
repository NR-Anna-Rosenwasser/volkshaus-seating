<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('seating:link {order : Order short id, e.g. o_abc123}')]
#[Description('Create a new personal link for an order. The previous link stops working.')]
class SelectionLink extends Command
{
    public function handle(): int
    {
        $order = Order::where('short_id', $this->argument('order'))->first();

        if ($order === null) {
            $this->error('No such order.');

            return self::FAILURE;
        }

        $this->line(route('selection.show', $order->issueToken()));

        return self::SUCCESS;
    }
}
