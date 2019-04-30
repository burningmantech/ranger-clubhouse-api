<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\Help;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class HelpPolicy
{
    use HandlesAuthorization;

    public function before($user)
    {
        if ($user->hasRole(Role::ADMIN)) {
            return true;
        }
    }

    /**
     * Determine whether the user can create a help document.
     */
    public function store(Person $user)
    {
        return false;
    }

    /**
     * Determine whether the user can update the help.
     */
    public function update(Person $user, Help $help)
    {
        return false;
    }

    /**
     * Determine whether the user can delete the help. (FIRE THE HELP!)
     */
    public function destroy(Person $user, Help $help)
    {
        return false;
    }
}
