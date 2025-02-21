<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\Role;
use App\Models\Swag;
use Illuminate\Auth\Access\HandlesAuthorization;

class SwagPolicy
{
    use HandlesAuthorization;

    public function before($user) : ?true
    {
        if ($user->hasRole([Role::ADMIN, Role::EDIT_SWAG])) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view the swag.
     *
     * @param Person $user
     * @param Swag $swag
     * @return bool
     */

    public function view(Person $user, Swag $swag): bool
    {
        return $user->hasRole(Role::QUARTERMASTER);
    }

    /**
     * Determine whether the user can create swags.
     *
     * @param Person $user
     * @return bool
     */
    public function store(Person $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the swag.
     *
     * @param Person $user
     * @param Swag $swag
     * @return bool
     */

    public function update(Person $user, Swag $swag): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the swag.
     *
     * @param Person $user
     * @param Swag $swag
     * @return bool
     */
    public function delete(Person $user, Swag $swag): bool
    {
        return false;
    }

    /**
     * Determine if the user can see a person's swags
     *
     * @param Person $user
     * @param Person $person
     * @return bool
     */

    public function personSwags(Person $user, Person $person): bool
    {
        return $user->id == $person->id || $user->hasRole(Role::QUARTERMASTER);
    }

    /**
     *  Can the user run the Potential Swag Report?
     *
     * @param Person $user
     * @return bool
     */

    public function potentialSwagReport(Person $user): bool
    {
        return $user->hasRole(Role::QUARTERMASTER);
    }
}
