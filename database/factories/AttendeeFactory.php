<?php

namespace Database\Factories;

use App\Enums\AttendeeStatus;
use App\Models\Attendee;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendee>
 */
class AttendeeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'hievents_attendee_id' => (string) fake()->unique()->numberBetween(1, 10_000_000),
            'hievents_product_id' => '1',
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'status' => AttendeeStatus::Active,
            'needs_seat' => true,
        ];
    }
}
