<?php

namespace App\Policies;

use App\Models\User;

class LocationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_location');
    }

    public function view(User $user): bool
    {
        return $user->can('view_location');
    }

    public function create(User $user): bool
    {
        return $user->can('create_location');
    }

    public function update(User $user): bool
    {
        return $user->can('update_location');
    }

    public function delete(User $user): bool
    {
        return $user->can('delete_location');
    }
}
