<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\Role;
use App\Models\Team;

use Illuminate\Auth\Access\HandlesAuthorization;

class TeamPolicy
{
    use HandlesAuthorization;

    public function before($user)
    {
        if ($user->hasRole(Role::TECH_NINJA)) {
            return true;
        }
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
        return false;
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
        return false;
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
        return false;
    }

    /**
     * Determine whether the user can run the People by Teams Report
     *
     * @param Person $user
     * @return false
     */

    public function peopleByTeamsReport(Person $user): bool
    {
        return $user->hasRole(Role::MANAGE);
    }

}
