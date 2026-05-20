<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'employee_number' => 'EMP-' . fake()->unique()->numberBetween(1000, 9999),
            'department_id' => Department::factory(),
            'position' => fake()->jobTitle(),
            'initials' => strtoupper(fake()->randomLetter() . fake()->randomLetter()),
            'phone' => fake()->phoneNumber(),
        ];
    }
}
