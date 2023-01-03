<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\PersonPog;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class PersonPogPolicy
{
    use HandlesAuthorization;

    public function before(Person $user)
    {
        if ($user->hasRole([Role::ADMIN, Role::MANAGE])) {
            return true;
        }
    }

    /**
     * Determine whether the user see all the pogs
     *
     * @param Person $user
     * @param $personId
     * @return bool
     */

    public function index(Person $user, $personId): bool
    {
        return $personId == $user->id;
    }

    /**
     * Determine whether the user can view a pog
     */

    public function show(Person $user, PersonPog $swag): bool
    {
        return $swag->person_id == $user->id;
    }

    /**
     * Determine whether the user can create a person swag.
     */

    public function store(Person $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update a person pog.
     */

    public function update(Person $user, PersonPog $swag): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the pog.
     */

    public function destroy(Person $user, PersonPog $swag): bool
    {
        return false;
    }
}
