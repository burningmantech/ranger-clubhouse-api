<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\PersonSwag;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class PersonSwagPolicy
{
    use HandlesAuthorization;

    public function before(Person $user) : ?true
    {
        if ($user->hasRole([Role::ADMIN, Role::QUARTERMASTER])) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user see all the swags
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
     * Determine whether the user can view a swag
     */

    public function show(Person $user, PersonSwag $swag): bool
    {
        return $swag->person_id == $user->id;
    }

    /**
     * Determine whether the user can update a person swag.
     */

    public function store(Person $user, $personId): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update a person swag.
     */

    public function update(Person $user, PersonSwag $swag): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the swag.
     */

    public function destroy(Person $user, PersonSwag $swag): bool
    {
        return false;
    }

    /**
     * Can the user run the swag distribution report?
     *
     * @param Person $user
     * @return bool
     */

    public function distribution(Person $user) : bool
    {
        return false;
    }
}
