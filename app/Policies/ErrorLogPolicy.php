<?php

namespace App\Policies;

use App\Models\ErrorLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ErrorLogPolicy
{
    use HandlesAuthorization;

    public function before($user)
    {
        if ($user->hasRole(Role::ADMIN)) {
            return true;
        }
    }

    /**
     * Determine whether the user can see error Log
     */
    public function index(Person $user)
    {
        return false;
    }
}
