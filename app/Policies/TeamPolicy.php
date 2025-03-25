<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\Role;
use App\Models\Team;
use App\Models\TeamManager;
use Illuminate\Auth\Access\HandlesAuthorization;

class TeamPolicy
{
    use HandlesAuthorization;

    public function before(Person $user): ?bool
    {
        return $user->hasRole(Role::TECH_NINJA) ? true : null;
    }

    /**
     * Determine whether the user can view the Team.
     *
     * @param Person $user
     * @param Team $team
     * @return bool
     */

    public function view(Person $user, Team $team): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create Teams.
     *
     * @param Person $user
     * @return bool
     */

    public function store(Person $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can update the Team.
     *
     * @param Person $user
     * @param Team $team
     * @return bool
     */

    public function update(Person $user, Team $team): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can delete the Team.
     *
     * @param Person $user
     * @param Team $team
     * @return false
     */

    public function delete(Person $user, Team $team): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can run the People by Teams Report
     *
     * @param Person $user
     * @return false
     */

    public function peopleByTeamsReport(Person $user): bool
    {
        return $user->hasRole(Role::EVENT_MANAGEMENT);
    }

    /**
     * Can the user bulk revoke or grant a team membership?
     *
     * @param Person $user
     * @param Team $team
     * @return bool
     */

    public function bulkGrantRevoke(Person $user, Team $team): bool
    {
        return $user->isAdmin() || TeamManager::isManager($team->id, $user->id);
    }

    /**
     * Can the user see the membership roster
     *
     * @param Person $user
     * @param Team $team
     * @return bool
     */

    public function membership(Person $user, Team $team): bool
    {
        return $user->isAdmin() || TeamManager::isManager($team->id, $user->id);
    }

    /**
     * Can the user see the directory?
     *
     * @param Person $user
     * @return bool
     */

    public function directory(Person $user): bool
    {
        return in_array($user->status, Person::ACTIVE_STATUSES);
    }
}
