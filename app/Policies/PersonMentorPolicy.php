<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\PersonMentor;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class PersonMentorPolicy
{
    use HandlesAuthorization;

    public function before($user)
    {
        if ($user->hasRole([ Role::ADMIN, Role::MENTOR ])) {
            return true;
        }
    }

    /*
     * Can a user see all the Clubhouse mentees?
     *
     * "computer says no.. " ya gotta be a Mentor!
     */

    public function mentees(Person $user)
    {
        return false;
    }
}
