<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmailHistoryPolicy
{
    use HandlesAuthorization;

    public function before($user)
    {
        if ($user->hasRole([Role::ADMIN, Role::VC, Role::VIEW_EMAIL])) {
            return true;
        }
    }

    /**
     * Determine whether the user can see email histories.
     *
     * @param Person $user
     * @return bool
     */

    public function index(Person $user): bool
    {
        return false;
    }
}
