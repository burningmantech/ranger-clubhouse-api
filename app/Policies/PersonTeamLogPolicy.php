<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\PersonTeamLog;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class PersonTeamLogPolicy
{
    use HandlesAuthorization;

    public function before($user)
    {
        if ($user->hasRole(Role::ADMIN)) {
            return true;
        }
    }

    /**
     * Can the user query a team history
     *
     * @param Person $user
     * @param $personId
     * @return bool
     */

    public function index(Person $user, $personId): bool
    {
        return ($personId == $user->id) || $user->hasRole(Role::EVENT_MANAGEMENT);
    }

    /**
     * Determine whether the user can view the Team.
     *
     * @param Person $user
     * @param PersonTeamLog $personTeamLog
     * @return bool
     */

    public function view(Person $user, PersonTeamLog $personTeamLog): bool
    {
        return ($user->id == $personTeamLog->person_id) || $user->hasRole(Role::EVENT_MANAGEMENT);
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
     * Determine whether the user can update a person's team history.
     *
     * @param Person $user
     * @param PersonTeamLog $personTeamLog
     * @return bool
     */

    public function update(Person $user, PersonTeamLog $personTeamLog): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete a person's team history.
     *
     * @param Person $user
     * @param PersonTeamLog $personTeamLog
     * @return bool
     */

    public function delete(Person $user, PersonTeamLog $personTeamLog): bool
    {
        return false;
    }
}
