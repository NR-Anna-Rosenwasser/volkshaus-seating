<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'hievents_event_id' => (string) fake()->unique()->numberBetween(1, 10_000_000),
            'name' => fake()->unique()->words(3, true),
            'venue_name' => 'Testsaal',
            'seated_product_ids' => ['1', '2'],
            'contact_email' => 'kontakt@example.ch',
            'starts_at' => now()->addDays(40),
            'doors_at' => now()->addDays(40)->subHour(),
            'selection_opens_at' => now()->subDay(),
            'selection_deadline' => now()->addDays(10),
        ];
    }
}
