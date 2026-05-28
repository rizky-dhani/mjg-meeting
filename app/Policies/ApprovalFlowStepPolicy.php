<?php

namespace App\Policies;

use App\Models\User;

class ApprovalFlowStepPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_approval_flow_step');
    }

    public function view(User $user): bool
    {
        return $user->can('view_approval_flow_step');
    }

    public function create(User $user): bool
    {
        return $user->can('create_approval_flow_step');
    }

    public function update(User $user): bool
    {
        return $user->can('update_approval_flow_step');
    }

    public function delete(User $user): bool
    {
        return $user->can('delete_approval_flow_step');
    }
}
