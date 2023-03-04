<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\PersonOnlineTraining;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class PersonOnlineTrainingPolicy
{
    use HandlesAuthorization;

    public function before($user)
    {
        if ($user->hasRole(Role::TECH_NINJA)) {
            return true;
        }
    }

    /**
     * Determine whether the user can view the online trainings.
     *
     * @param Person $user
     * @param PersonOnlineTraining $personOT
     * @return bool
     */

    public function view(Person $user, PersonOnlineTraining $personOT): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create online training.
     *
     * @param Person $user
     * @return bool
     */
    public function store(Person $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the online training.
     *
     * @param Person $user
     * @param PersonOnlineTraining $personOT
     * @return bool
     */
    public function update(Person $user, PersonOnlineTraining $personOT): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the online training.
     *
     * @param Person $user
     * @param PersonOnlineTraining $personOT
     * @return bool
     */
    public function delete(Person $user, PersonOnlineTraining $personOT): bool
    {
        return false;
    }

    /**
     * Can the user import the online training course completions?
     */

    public function import(Person $user) : bool
    {
        return false; // only tech ninjas
    }

    /*
     * Can the user see the online training configuration?
     */

    public function config(Person $user) : bool
    {
        return false; // only tech ninjas
    }

    /*
     * Can the user see the courses
     */

    public function courses(Person $user) : bool
    {
        return false;
    }

    /*
     * Can the user see the enrollment list.
     */

    public function enrollment(Person $user) : bool
    {
        return false;
    }

    /*
     * Can the user update the course types
     */

    public function setCourseType(Person $user) : bool
    {
        return false;
    }

    /*
     * Can the user setup an online training account
     */

    public function setupPerson(Person $user, Person $person): bool
    {
        return $user->id == $person->id;
    }

    public function resetPassword(Person $user, Person $person) : bool
    {
        return $user->id == $person->id || $user->hasRole(Role::TRAINER);
    }

    public function markCompleted(Person $user): bool
    {
        return false;
    }
}
