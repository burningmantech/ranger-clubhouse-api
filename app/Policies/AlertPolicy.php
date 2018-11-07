<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\Alert;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class AlertPolicy
{
    use HandlesAuthorization;

    public function before($user)
    {
        if ($user->hasRole(Role::ADMIN)) {
            return true;
        }
    }

    /**
     * Determine whether the user can create positions.
     */
    public function store(Person $user)
    {
        return false;
    }

    /**
     * Determine whether the user can update the position.
     */
    public function update(Person $user, Alert $alert)
    {
        return false;
    }

    /**
     * Determine whether the user can delete the position.
     */
    public function delete(Person $user, Alert $alert)
    {
        return false;
    }
}
