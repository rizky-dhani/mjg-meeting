<?php

namespace Database\Seeders;

use App\Models\Division;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $it = Division::where('name', 'Information Technology')->first();
        $hr = Division::where('name', 'Human Resources')->first();

        // Super Admin
        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@medquest.co.id'],
            [
                'name' => 'Super Admin',
                'password' => 'Superadmin2026!',
                'employee_code' => 'SUP-001',
                'division_id' => $it?->id,
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
                'division_id' => $it?->id,
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
                'division_id' => $hr?->id,
                'initial' => 'HED',
            ]
        );
        $head->assignRole('Head');
    }
}
