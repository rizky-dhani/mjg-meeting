<?php

namespace Database\Seeders;

use App\Models\ApprovalFlow;
use App\Models\Role;
use Illuminate\Database\Seeder;

class ApprovalFlowSeeder extends Seeder
{
    public function run(): void
    {
        $flow = ApprovalFlow::create([
            'name' => 'Booking Approval',
            'model_type' => 'App\Models\Booking',
            'description' => 'Standard booking approval workflow requiring requester and management approval.',
        ]);

        $userRole = Role::where('name', 'User')->first();
        $adminRole = Role::where('name', 'Admin')->first();

        if ($userRole) {
            $flow->steps()->create([
                'role_id' => $userRole->id,
                'step_order' => 1,
            ]);
        }

        if ($adminRole) {
            $flow->steps()->create([
                'role_id' => $adminRole->id,
                'step_order' => 2,
            ]);
        }
    }
}
