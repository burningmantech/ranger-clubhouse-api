<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class RolePolicy
{
    use HandlesAuthorization;

    public function before($user)
    {
        if ($user->hasRole(Role::ADMIN)) {
            return true;
        }
    }

    /**
     * Determine whether the user can view the role.
     *
     * @param Person $user
     * @param Role $role
     * @return bool
     */

    public function view(Person $user, Role $role): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create roles.
     *
     * @param Person $user
     * @return bool
     */

    public function store(Person $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the role.
     *
     * @param Person $user
     * @param Role $role
     * @return bool
     */

    public function update(Person $user, Role $role): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the role.
     *
     * @param Person $user
     * @param Role $role
     * @return bool
     */

    public function delete(Person $user, Role $role): bool
    {
        return false;
    }


    /**
     * Can the person run the People By Role Report?
     *
     * @param Person $user
     * @return bool
     */

    public function peopleByRole(Person $user): bool
    {
        return $user->hasRole([Role::ADMIN, Role::MANAGE]);
    }

    /**
     * Can the person clear the cached roles for a person.
     *
     * @param Person $user
     * @return bool
     */

    public function clearCache(Person $user) : bool
    {
        return $user->hasRole(Role::TECH_NINJA);
    }

    /**
     * Can the person inspect the cached roles for a person.
     *
     * @param Person $user
     * @return bool
     */

    public function inspectCache(Person $user) : bool
    {
        return $user->hasRole(Role::TECH_NINJA);
    }
}
