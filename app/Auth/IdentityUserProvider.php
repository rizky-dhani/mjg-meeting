<?php

namespace App\Auth;

use App\Models\Identity\User as IdentityUser;
use App\Models\User;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

class IdentityUserProvider extends EloquentUserProvider
{
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        if (empty($credentials['email'])) {
            return null;
        }

        // Step 1: find active identity user by email
        $identityUser = IdentityUser::where('email', $credentials['email'])
            ->where('is_active', true)
            ->first();
        if (! $identityUser) {
            return null;
        }

        // Step 2: find local user by user_id
        return User::where('user_id', $identityUser->userId)->first();
    }
}
