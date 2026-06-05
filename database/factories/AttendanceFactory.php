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
            'user_id' => fn (array $attrs) => isset($attrs['guest_name']) ? null : User::factory(),
            'guest_name' => null,
            'guest_from' => null,
            'guest_designation' => null,
            'checked_in_at' => fake()->dateTimeBetween('-1 hour', 'now'),
        ];
    }

    public function guest(): static
    {
        return $this->state(fn (array $attrs) => [
            'user_id' => null,
            'guest_name' => fake()->name(),
            'guest_from' => fake()->company(),
            'guest_designation' => fake()->randomElement(['Vendor PIC', 'Consultant', 'Client Representative', 'External Auditor']),
        ]);
    }
}
