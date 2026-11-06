<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            // Most tests have one event, which every order then shares.
            'event_id' => fn (): int => Event::query()->value('id') ?? Event::factory()->create()->id,
            'hievents_order_id' => (string) fake()->unique()->numberBetween(1, 10_000_000),
            'short_id' => 'o_'.Str::random(8),
            'email' => fake()->unique()->safeEmail(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'locale' => 'de-CH',
            'status' => OrderStatus::Active,
            'token_hash' => hash('sha256', Str::random(43)),
            'created_at' => now(),
        ];
    }
}
