<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class ActionLogPolicy
{
    use HandlesAuthorization;

    public function before($user)
    {
        if ($user->hasRole(Role::ADMIN)) {
            return true;
        }
    }

    /**
     * Determine whether the user can see action Log
     * @param Person $user
     * @return bool
     */

    public function index(Person $user): bool
    {
        return $user->hasRole(Role::EVENT_MANAGEMENT);
    }
}
