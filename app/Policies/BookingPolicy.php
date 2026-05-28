<?php

namespace App\Policies;

use App\Models\User;

class BookingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_booking');
    }

    public function view(User $user): bool
    {
        return $user->can('view_booking');
    }

    public function create(User $user): bool
    {
        return $user->can('create_booking');
    }

    public function update(User $user): bool
    {
        return $user->can('update_booking');
    }

    public function delete(User $user): bool
    {
        return $user->can('delete_booking');
    }
}
