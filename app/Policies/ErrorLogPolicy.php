<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class ErrorLogPolicy
{
    use HandlesAuthorization;

    public function before($user)
    {
        if ($user->hasRole(Role::TECH_NINJA)) {
            return true;
        }
    }

    /**
     * Determine whether the user can see error Log
     */
    public function index(Person $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can purge the log
     */
    public function purge(Person $user): bool
    {
        return false;
    }
}
