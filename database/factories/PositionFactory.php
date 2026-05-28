<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Position>
 */
class PositionFactory extends Factory
{
    public function definition(): array
    {
        $positions = ['Manager', 'Supervisor', 'Staff', 'Lead', 'Coordinator', 'Specialist', 'Analyst'];

        return [
            'department_id' => Department::factory(),
            'name' => fake()->randomElement($positions) . ' - ' . fake()->word(),
            'description' => fake()->sentence(),
        ];
    }
}
