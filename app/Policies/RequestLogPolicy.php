<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class RequestLogPolicy
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

    public function index(Person $user): false
    {
        return false;
    }

    /**
     * Determine if the user can expire the log
     * @param Person $user
     * @return false
     */
    public function expire(Person $user): false
    {
        return false;
    }
}
