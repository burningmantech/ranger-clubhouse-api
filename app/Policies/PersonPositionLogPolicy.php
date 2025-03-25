<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\PersonPositionLog;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class PersonPositionLogPolicy
{
    use HandlesAuthorization;

    public function before($user)
    {
        if ($user->hasRole(Role::ADMIN)) {
            return true;
        }
    }

    /**
     * Can the user show the position log?
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
     * Determine whether the user can view the position log.
     *
     * @param Person $user
     * @param PersonPositionLog $PersonPositionLog
     * @return bool
     */

    public function view(Person $user, PersonPositionLog $PersonPositionLog): bool
    {
        return ($user->id == $PersonPositionLog->person_id) || $user->hasRole(Role::EVENT_MANAGEMENT);
    }

    /**
     * Determine whether the user can create a position log record.
     *
     * @param Person $user
     * @return bool
     */

    public function store(Person $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update a person's position log
     *
     * @param Person $user
     * @param PersonPositionLog $PersonPositionLog
     * @return bool
     */

    public function update(Person $user, PersonPositionLog $PersonPositionLog): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete a person's position log
     *
     * @param Person $user
     * @param PersonPositionLog $PersonPositionLog
     * @return bool
     */

    public function delete(Person $user, PersonPositionLog $PersonPositionLog): bool
    {
        return false;
    }
}
