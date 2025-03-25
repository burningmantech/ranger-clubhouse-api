<?php


namespace App\Policies;

use App\Models\Person;
use App\Models\Position;
use App\Models\Role;
use App\Models\TeamManager;
use Illuminate\Auth\Access\HandlesAuthorization;

class PositionPolicy
{
    use HandlesAuthorization;

    public function before(Person $user): ?bool
    {
        return $user->hasRole([Role::ADMIN, Role::TECH_NINJA]) ? true : null;
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
     * @return bool
     */

    public function store(Person $user): bool
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
        return $user->hasRole(Role::EVENT_MANAGEMENT);
    }

    /**
     * Determine if the person can run the people by teams report
     *
     * @param Person $user
     * @return bool
     */

    public function peopleByTeamsReport(Person $user): bool
    {
        return $user->hasRole(Role::EVENT_MANAGEMENT);
    }

    /**
     * Determine if the person can run the Position Sanity Checker
     *
     * @param Person $user
     * @return bool
     */

    public function sanityChecker(Person $user): bool
    {
        return false;
    }

    /**
     * Determine if the person can run the Position Sanity Checker
     *
     * @param Person $user
     * @return bool
     */

    public function repair(Person $user): bool
    {
        // Only admins  are allowed to run it.
        return false;
    }

    /**
     * Determine if the person can run bulk grant or revoke positions.
     *
     * @param Person $user
     * @param Position $position
     * @return bool
     */

    public function bulkGrantRevoke(Person $user, Position $position): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (!$position->team_id) {
            return false;
        }

        return TeamManager::isManager($position->team_id, $user->id);
    }

    /**
     * Determine who can run the people by position report
     *
     */

    public function peopleByPosition(Person $user): bool
    {
        return $user->hasRole(Role::EVENT_MANAGEMENT);
    }

    /**
     * Can the person see who has been granted a specific position?
     *
     * @param Person $user
     * @return bool
     */

    public function grants(Person $user): bool
    {
        return $user->hasRole(Role::EVENT_MANAGEMENT);
    }
}
