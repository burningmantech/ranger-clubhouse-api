<?php

namespace App\Policies;

use App\Models\ActionLog;
use App\Models\Role;
use App\Models\User;
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
     */
    public function index(Person $user)
    {
        return false;
    }

    /**
     * Determine whether the user can purge the log
     */
    public function purge(Person $user)
    {
        return false;
    }

}
