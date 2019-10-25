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
        if ($user->hasRole(Role::ADMIN)) {
            return true;
        }
    }

    /**
     * Determine whether the user can view the position.
     *
     * @param  \App\Models\Person  $user
     * @param  \App\App\Models\Position  $position
     * @return mixed
     */
    public function view(Person $user, Position $position)
    {
        return true;
    }

    /**
     * Determine whether the user can create positions.
     *
     * @param  \App\Models\Person  $user
     * @return mixed
     */
    public function store(Person $user)
    {
        return false;
    }

    /**
     * Determine whether the user can update the position.
     *
     * @param  \App\Models\Person  $user
     * @param  \App\App\Models\Position  $position
     * @return mixed
     */
    public function update(Person $user, Position $position)
    {
        return false;
    }

    /**
     * Determine whether the user can delete the position.
     *
     * @param  \App\Models\Person  $user
     * @param  \App\App\Models\Position  $position
     * @return mixed
     */
    public function delete(Person $user, Position $position)
    {
        return false;
    }

    /**
     * Determine if the person can run the qualified sandman report
     */

    public function sandmanQualified(Person $user)
    {
        return $user->hasRole(Role::MANAGE);
    }

    /**
     * Determine if the person can run the Position Sanity Checker
     */

    public function sanityChecker(Person $user)
    {
        return $user->hasRole([ Role::MANAGE, Role::GRANT_POSITION ]);
    }

    /**
     * Determine if the person can run the Position Sanity Checker
     */

    public function repair(Person $user)
    {
        // Only admins & Gran Positions are allowed to run it.
        return $user->hasRole(Role::GRANT_POSITION);
    }

}
