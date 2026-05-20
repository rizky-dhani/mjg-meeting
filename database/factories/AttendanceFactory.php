<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory()->approved(),
            'user_id' => User::factory(),
            'checked_in_at' => fake()->dateTimeBetween('-1 hour', 'now'),
        ];
    }
}
