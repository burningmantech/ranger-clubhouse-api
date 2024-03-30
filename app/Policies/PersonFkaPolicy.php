<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\PersonFka;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class PersonFkaPolicy
{
    use HandlesAuthorization;

    public function before($user): ?bool
    {
        if ($user->hasRole([Role::ADMIN, Role::MENTOR, Role::VC])) {
            return true;
        }

        return null;
    }

    /**
     * Can the person see a list of records?
     * @param Person $user
     * @param $personId
     * @return bool
     */

    public function index(Person $user, $personId): bool
    {
        return $user->id == $personId || $user->hasRole(Role::MANAGE);
    }

    /**
     * Can the person create a fka record
     *
     * @param Person $user
     * @param PersonFka $person_fka
     * @return true
     */

    public function show(Person $user, PersonFka $person_fka): true
    {
        return $user->id == $person_fka->person_id || $user->hasRole(Role::MANAGE);
    }

    /**
     * Can the person create a fka record
     *
     * @param Person $user
     * @param PersonFka $person_fka
     * @return false
     */

    public function store(Person $user, PersonFka $person_fka): false
    {
        return false;
    }

    /**
     * Determine whether the user can update the record.
     * @param Person $user
     * @param PersonFka $person_fka
     * @return false
     */

    public function update(Person $user, PersonFka $person_fka): false
    {
        return false;
    }

    /**
     * Determine whether the user can delete a record
     * @param Person $user
     * @param PersonFka $person_fka
     * @return false
     */

    public function destroy(Person $user, PersonFka $person_fka): false
    {
        return false;
    }
}
