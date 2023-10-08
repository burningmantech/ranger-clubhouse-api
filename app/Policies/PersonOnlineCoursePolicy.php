<?php

namespace App\Policies;

use App\Models\OnlineCourse;
use App\Models\Person;
use App\Models\PersonOnlineCourse;
use App\Models\Position;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class PersonOnlineCoursePolicy
{
    use HandlesAuthorization;

    public function before($user)
    {
        if ($user->hasRole(Role::TECH_NINJA)) {
            return true;
        }
    }


    /**
     * Determine if the user can update the online course enrollment
     */

    public function change(Person $user, Person $person, OnlineCourse $onlineCourse) : bool
    {
        return ($onlineCourse->position_id == Position::TRAINING && $user->hasTrueRole(Role::TRAINER));
    }

    /**
     * Can the user setup an online course account
     */

    public function setupPerson(Person $user, Person $person): bool
    {
        return $user->id == $person->id || $user->hasTrueRole(Role::TRAINER);
    }

    public function resetPassword(Person $user, Person $person) : bool
    {
        return $user->id == $person->id || $user->hasRole(Role::TRAINER);
    }

    public function markCompleted(Person $user): bool
    {
        return $user->hasRole(Role::TRAINER);
    }

    public function syncInfo(Person $user, Person $person) : bool
    {
        return $user->hasRole(Role::TRAINER);
    }

    public function getInfo(Person $user, Person $person) : bool
    {
        return $user->hasRole(Role::TRAINER);
    }

    public function courseInfo(Person $user, Person $person) : bool
    {
        return $user->hasRole(Role::TRAINER);
    }
}
