<?php

namespace App\Policies;

use App\Models\User;

class AttendancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_attendance');
    }

    public function view(User $user): bool
    {
        return $user->can('view_attendance');
    }

    public function create(User $user): bool
    {
        return $user->can('create_attendance');
    }

    public function update(User $user): bool
    {
        return $user->can('update_attendance');
    }

    public function delete(User $user): bool
    {
        return $user->can('delete_attendance');
    }
}
