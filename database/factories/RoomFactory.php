<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    public function definition(): array
    {
        return [
            'location_id' => Location::factory(),
            'name' => fake()->randomElement(['Meeting Room A', 'Meeting Room B', 'Conference Hall', 'Board Room', 'Training Room', 'Breakout Space']),
            'capacity' => fake()->randomElement([6, 8, 10, 15, 20, 30]),
            'description' => fake()->sentence(),
        ];
    }
}
