<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Super Admin
        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@medquest.co.id'],
            [
                'name' => 'Super Admin',
                'password' => 'Superadmin2026!',
                'employee_number' => 'SUP-001',
                'department_id' => Department::where('code', 'IT')->value('id'),
                'position' => 'System Administrator',
                'initials' => 'SPA',
            ]
        );
        $superAdmin->assignRole('Super Admin');

        // Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@medquest.co.id'],
            [
                'name' => 'Admin',
                'password' => 'Medquest.1',
                'employee_number' => 'ADM-002',
                'department_id' => Department::where('code', 'IT')->value('id'),
                'position' => 'Administrator',
                'initials' => 'ADM',
            ]
        );
        $admin->assignRole('Admin');

        // Head
        $head = User::firstOrCreate(
            ['email' => 'head@medquest.co.id'],
            [
                'name' => 'Head',
                'password' => 'Medquest.1',
                'employee_number' => 'HED-001',
                'department_id' => Department::where('code', 'HR')->value('id'),
                'position' => 'Department Head',
                'initials' => 'HED',
            ]
        );
        $head->assignRole('Head');
    }
}
