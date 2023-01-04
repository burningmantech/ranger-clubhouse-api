<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class MailLogPolicy
{
    use HandlesAuthorization;

    public function before($user)
    {
        if ($user->hasRole([Role::ADMIN, Role::VC])) {
            return true;
        }
    }

    /**
     * Determine whether the user can see mail Log
     */
    public function index(Person $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can see mail Log stats
     */
    public function stats(Person $user): bool
    {
        return false;
    }

}
