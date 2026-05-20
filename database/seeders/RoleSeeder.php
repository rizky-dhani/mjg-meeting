<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create roles
        Role::create(['name' => 'Super Admin']);
        Role::create(['name' => 'Admin']);
        Role::create(['name' => 'User']);

        // Create default admin (for dev/demo)
        $admin = User::firstOrCreate(
            ['email' => 'admin@meeting.test'],
            [
                'name' => 'Administrator',
                'password' => bcrypt('password'),
            ]
        );
        $admin->assignRole('Super Admin');

        // Create demo user with User role
        $demoUser = User::firstOrCreate(
            ['email' => 'user@meeting.test'],
            [
                'name' => 'Demo User',
                'password' => bcrypt('password'),
            ]
        );
        $demoUser->assignRole('User');
    }
}
