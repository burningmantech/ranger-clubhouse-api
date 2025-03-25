<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\PersonAward;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class PersonAwardPolicy
{
    use HandlesAuthorization;

    public function before(Person $user)
    {
        if ($user->hasRole(Role::ADMIN)) {
            return true;
        }
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
        return $personId == $user->id || $user->hasRole(Role::EVENT_MANAGEMENT);
    }

    /**
     * Determine whether the user can view an award
     */

    public function show(Person $user, PersonAward $award): bool
    {
        return $award->person_id == $user->id || $user->hasRole(Role::EVENT_MANAGEMENT);
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
}
