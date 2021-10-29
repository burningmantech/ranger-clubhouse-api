<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class AgreementsPolicy
{
    use HandlesAuthorization;

    /**
     *
     * Can the user see a person's agreements
     * @param Person $user
     * @param Person $person
     * @return bool
     */

    public function index(Person $user, Person $person): bool
    {
        return $person->id == $user->id || $user->hasRole(Role::MANAGE);
    }

    /**
     * Can the user see a particular agreement for person
     *
     * @param Person $user
     * @param Person $person
     * @return bool
     */

    public function show(Person $user, Person $person): bool
    {
        return $person->id == $user->id || $user->hasRole(Role::MANAGE);
    }

    /**
     * Can the user sign an agreement? (the person or Admin)
     *
     * @param Person $user
     * @param Person $person
     * @return bool
     */

    public function sign(Person $user, Person $person): bool
    {
        return $person->id == $user->id || $user->hasRole(Role::ADMIN);
    }
}