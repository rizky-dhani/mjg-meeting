<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $it = Department::where('name', 'Information Technology')->first();
        $hr = Department::where('name', 'Human Resources')->first();

        // Super Admin
        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@medquest.co.id'],
            [
                'name' => 'Super Admin',
                'password' => 'Superadmin2026!',
                'employee_code' => 'SUP-001',
                'department_id' => $it?->department_id,
                'initial' => 'SPA',
            ]
        );
        $superAdmin->assignRole('Super Admin');

        // Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@medquest.co.id'],
            [
                'name' => 'Admin',
                'password' => 'Medquest.1',
                'employee_code' => 'ADM-002',
                'department_id' => $it?->department_id,
                'initial' => 'ADM',
            ]
        );
        $admin->assignRole('Admin');

        // Head
        $head = User::firstOrCreate(
            ['email' => 'head@medquest.co.id'],
            [
                'name' => 'Head',
                'password' => 'Medquest.1',
                'employee_code' => 'HED-001',
                'department_id' => $hr?->department_id,
                'initial' => 'HED',
            ]
        );
        $head->assignRole('Head');
    }
}
