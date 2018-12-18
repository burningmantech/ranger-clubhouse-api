<?php

namespace App\Policies;

use App\Models\TrainingSession;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class TrainingSessionPolicy
{
    use HandlesAuthorization;

    public function before($user)
    {
        if ($user->hasRole([ Role::ADMIN, Role::TRAINER, Role::MENTOR, Role::VC ])) {
            return true;
        }
    }

    /**
     * Can a user see the training session(s)?
     */

    public function show(Person $user)
    {
        return false;
    }

    /**
     *  Can the user score (mark passed, add notes, etc.) to a session?
     */

    public function score(Person $user)
    {
        return false;
    }

    /**
     * Can the user add or remove a person to/from a session?
     *
     */

    public function admissions(Person $user)
    {
        return false;
    }
}
