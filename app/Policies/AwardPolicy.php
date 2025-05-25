<?php

namespace App\Policies;

use App\Models\Award;
use App\Models\Person;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class AwardPolicy
{
    use HandlesAuthorization;

    public function before($user)
    {
        if ($user->hasRole(Role::ADMIN)) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view the award.
     *
     * @param Person $user
     * @param Award $award
     * @return bool
     */

    public function view(Person $user, Award $award): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create awards.
     *
     * @param Person $user
     * @return bool
     */
    public function store(Person $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the award.
     *
     * @param Person $user
     * @param Award $award
     * @return bool
     */

    public function update(Person $user, Award $award): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the award.
     *
     * @param Person $user
     * @param Award $award
     * @return bool
     */
    public function delete(Person $user, Award $award): bool
    {
        return false;
    }

    /**
     * Determine if the user can see a person's awards
     *
     * @param Person $user
     * @param Person $person
     * @return bool
     */

    public function personAwards(Person $user, Person $person): bool
    {
        return $user->id == $person->id || $user->hasRole(Role::EVENT_MANAGEMENT);
    }

    /**
     * Determine if the user can bulk grant awards based on service years
     *
     * @param Person $user
     * @return bool
     */

    public function bulkGrantServiceYearsAward(Person $user): bool
    {
        return false;
    }

    /**
     * Determine if the user can bulk grant awards
     *
     * @param Person $user
     * @return bool
     */

    public function bulkGrantAward(Person $user): bool
    {
        return false;
    }

    /**
     * Determine if the user can run the service years award report
     *
     * @param Person $user
     * @return bool
     */

    public function serviceYearsReport(Person $user): bool
    {
        if ($user->hasRole([Role::QUARTERMASTER, Role::ADMIN])) {
            return true;
        }

        return null;
    }

}
