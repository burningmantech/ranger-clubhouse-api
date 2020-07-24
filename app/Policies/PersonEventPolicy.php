<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\PersonEvent;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class PersonEventPolicy
{
    use HandlesAuthorization;

    public function before($user)
    {
        if ($user->hasRole(Role::ADMIN)) {
            return true;
        }
    }

    /**
     * Determine whether the user see all the person event records
     */
    public function index(Person $user)
    {
        return $user->hasRole(Role::MANAGE);
    }

    /**
     * Determine whether the user can view the record.
     */
    public function show(Person $user, PersonEvent $personEvent)
    {
        return ($user->id == $personEvent->person_id) || $user->hasRole(Role::MANAGE);
    }

    /**
     * Determine whether the user can create the person event record.
     *
     */
    public function store(Person $user, $personId)
    {
        return ($user->id == $personId) || $user->hasRole(Role::MANAGE);
    }

    /**
     * Determine whether the user can update the asset.
     */
    public function update(Person $user, PersonEvent $personEvent)
    {
        return ($user->id == $personEvent->person_id) || $user->hasRole(Role::MANAGE);
    }

    /**
     * Determine whether the user can delete the record.
     *
     */
    public function destroy(Person $user, PersonEvent $personEvent)
    {
        return false;
    }
}
