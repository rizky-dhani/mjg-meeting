<?php

namespace App\Policies;

use App\Models\User;

class PositionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_position');
    }

    public function view(User $user): bool
    {
        return $user->can('view_position');
    }

    public function create(User $user): bool
    {
        return $user->can('create_position');
    }

    public function update(User $user): bool
    {
        return $user->can('update_position');
    }

    public function delete(User $user): bool
    {
        return $user->can('delete_position');
    }
}
