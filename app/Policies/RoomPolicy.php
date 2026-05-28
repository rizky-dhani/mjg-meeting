<?php

namespace App\Policies;

use App\Models\User;

class RoomPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_room');
    }

    public function view(User $user): bool
    {
        return $user->can('view_room');
    }

    public function create(User $user): bool
    {
        return $user->can('create_room');
    }

    public function update(User $user): bool
    {
        return $user->can('update_room');
    }

    public function delete(User $user): bool
    {
        return $user->can('delete_room');
    }
}
