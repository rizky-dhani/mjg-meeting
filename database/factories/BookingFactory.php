<?php

namespace Database\Factories;

use App\Models\ApprovalFlow;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('+1 day', '+1 week');
        $endsAt = (clone $startsAt)->modify('+1 hour');
        $date = $startsAt->format('Y-m-d');

        return [
            'room_id' => Room::factory(),
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'date' => $date,
            'starts_at' => $startsAt->format('H:i:s'),
            'ends_at' => $endsAt->format('H:i:s'),
        ];
    }

    public function approved(): static
    {
        return $this->afterCreating(function (Booking $booking) {
            $flow = ApprovalFlow::where('model_type', Booking::class)->first();

            if ($flow === null || ! $flow->steps()->exists()) {
                return;
            }

            foreach ($flow->steps as $step) {
                $booking->approvals()->create([
                    'key' => $flow->name,
                    'approval_flow_step_id' => $step->id,
                    'status' => 'approved',
                    'approver_id' => User::factory(),
                    'approver_type' => User::class,
                ]);
            }
        });
    }
}
