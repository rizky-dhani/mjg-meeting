<?php

namespace App\Policies;

use App\Models\User;

class DivisionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_division');
    }

    public function view(User $user): bool
    {
        return $user->can('view_division');
    }

    public function create(User $user): bool
    {
        return $user->can('create_division');
    }

    public function update(User $user): bool
    {
        return $user->can('update_division');
    }

    public function delete(User $user): bool
    {
        return $user->can('delete_division');
    }
}
