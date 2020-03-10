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
        if ($user->hasRole(Role::ADMIN)) {
            return true;
        }
    }

    /**
     * Determine whether the user can view the online trainings.
     *
     * @param Person $user
     * @param \App\App\Models\PersonOnlineTraining $personOT
     * @return mixed
     */
    public function view(Person $user, PersonOnlineTraining $personOT)
    {
        return false;
    }

    /**
     * Determine whether the user can create online training.
     *
     * @param Person $user
     * @return mixed
     */
    public function store(Person $user)
    {
        return false;
    }

    /**
     * Determine whether the user can update the online training.
     *
     * @param Person $user
     * @param \App\App\Models\PersonOnlineTraining $personOT
     * @return mixed
     */
    public function update(Person $user, PersonOnlineTraining $personOT)
    {
        return false;
    }

    /**
     * Determine whether the user can delete the online training.
     *
     * @param Person $user
     * @param \App\App\Models\PersonOnlineTraining $personOT
     * @return mixed
     */
    public function delete(Person $user, PersonOnlineTraining $personOT)
    {
        return false;
    }

    /*
     * Can the user import the online training course completions?
     */

    public function import(Person $user)
    {
        return false; // only admins
    }

    /*
     * Can the user see the online training configuration?
     */

    public function config(Person $user)
    {
        return false; // only admins
    }

    /*
     * Can the user see the courses
     */

    public function courses(Person $user)
    {
        return false; // only admins
    }

    /*
     * Can the user see the enrollment list.
     */

    public function enrollment(Person $user)
    {
        return false; // only admins
    }

    /*
     * Can the user setup an online training account
     */

    public function setupPerson(Person $user, Person $person)
    {
        return $user->id == $person->id;
    }
}
