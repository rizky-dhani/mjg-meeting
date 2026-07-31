<?php

namespace Database\Seeders;

use App\Models\Division;
use App\Models\Location;
use App\Models\Room;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Demo departments (created first so seeders can reference them)
        Division::firstOrCreate(['name' => 'Information Technology'], ['division_id' => Str::uuid(), 'initial' => 'IT']);
        Division::firstOrCreate(['name' => 'Human Resources'], ['division_id' => Str::uuid(), 'initial' => 'HR']);
        Division::firstOrCreate(['name' => 'Marketing'], ['division_id' => Str::uuid(), 'initial' => 'MKT']);

        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            ApprovalFlowSeeder::class,
        ]);

        // Demo locations
        $headOffice = Location::create(['name' => 'Head Office', 'address' => '123 Main St']);
        $warehouse = Location::create(['name' => 'Warehouse', 'address' => '456 Industrial Ave']);

        // Demo rooms
        Room::create(['location_id' => $headOffice->id, 'name' => 'Meeting Room A', 'capacity' => 10]);
        Room::create(['location_id' => $headOffice->id, 'name' => 'Meeting Room B', 'capacity' => 8]);
        Room::create(['location_id' => $headOffice->id, 'name' => 'Conference Hall', 'capacity' => 30]);
        Room::create(['location_id' => $headOffice->id, 'name' => 'Board Room', 'capacity' => 15]);
        Room::create(['location_id' => $warehouse->id, 'name' => 'Training Room', 'capacity' => 20]);
        Room::create(['location_id' => $warehouse->id, 'name' => 'Breakout Space', 'capacity' => 6]);
    }
}
