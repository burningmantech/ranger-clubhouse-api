<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\PersonAward;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class PersonAwardPolicy
{
    use HandlesAuthorization;

    public function before(Person $user): ?true
    {
        if ($user->hasRole([Role::ADMIN, Role::AWARD_MANAGEMENT])) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user see all the awards
     *
     * @param Person $user
     * @param $personId
     * @return bool
     */

    public function index(Person $user, $personId): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view an award
     */

    public function show(Person $user, PersonAward $award): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update a person award.
     */

    public function store(Person $user, $personId): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update a person award.
     */

    public function update(Person $user, PersonAward $award): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the award.
     */

    public function destroy(Person $user, PersonAward $award): bool
    {
        return false;
    }

    public function bulkGrant(Person $user) : bool
    {
        return false;
    }

    public function awardsForPerson(Person $user, Person $person): bool
    {
        return $user->hasRole(Role::EVENT_MANAGEMENT) || $person->id == $user->id;
    }

    public function rebuildPerson(Person $user, Person $person): bool
    {
        return false;
    }

    public function rebuildAllAwards(Person $user) : bool
    {
        return false;
    }

}
