<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\Position;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class PositionPolicy
{
    use HandlesAuthorization;

    public function before($user)
    {
        if ($user->hasRole(Role::TECH_NINJA)) {
            return true;
        }
    }

    /**
     * Determine whether the user can view the position.
     *
     * @param Person $user
     * @param Position $position
     * @return true
     */
    public function view(Person $user, Position $position): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create positions.
     *
     * @param Person $user
     * @return false
     */
    public function store(Person $user): mixed
    {
        return false;
    }

    /**
     * Determine whether the user can update the position.
     *
     * @param Person $user
     * @param Position $position
     * @return false
     */
    public function update(Person $user, Position $position): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the position.
     *
     * @param Person $user
     * @param Position $position
     * @return false
     */
    public function delete(Person $user, Position $position): bool
    {
        return false;
    }

    /**
     * Determine if the person can run the qualified sandman report
     *
     * @param Person $user
     * @return bool
     */

    public function sandmanQualified(Person $user): bool
    {
        return $user->hasRole([ Role::ADMIN, Role::MANAGE]);
    }

    /**
     * Determine if the person can run the Position Sanity Checker
     *
     * @param Person $user
     * @return bool
     */

    public function sanityChecker(Person $user): bool
    {
        return $user->hasRole([ Role::ADMIN, Role::MANAGE, Role::GRANT_POSITION]);
    }

    /**
     * Determine if the person can run the Position Sanity Checker
     * @param Person $user
     * @return bool
     */

    public function repair(Person $user): bool
    {
        // Only admins & Gran Positions are allowed to run it.
        return $user->hasRole([ Role::ADMIN, Role::GRANT_POSITION]);
    }

    /**
     * Determine if the person can run bulk grant or revoke positions.
     * @param Person $user
     * @return bool
     */

    public function bulkGrantRevoke(Person $user): bool
    {
        // Only admins are allowed to run it.
        return $user->hasRole(Role::ADMIN);
    }
}
