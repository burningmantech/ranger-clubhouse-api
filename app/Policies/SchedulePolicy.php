<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class SchedulePolicy
{
    use HandlesAuthorization;

    public function before(Person $user)
    {
        if ($user->hasRole([Role::EVENT_MANAGEMENT, Role::ADMIN, Role::VC, Role::TRAINER, Role::MENTOR])) {
            return true;
        }
    }

    /**
     * Determine whether the user can view the personSchedule.
     *
     * @param Person $user
     * @param Person $person
     * @return bool
     */

    public function view(Person $user, Person $person) : bool
    {
        return ($user->id == $person->id);
    }

    /**
     * Determine whether the user can create personSchedules.
     *
     * @param Person $user
     * @param Person $person
     * @return bool
     */

    public function create(Person $user, Person $person) : bool
    {
        return ($user->id == $person->id);
    }

    /**
     * Determine whether the user can update the personSchedule.
     *
     * @param Person $user
     * @param Person $person
     * @return bool
     */

    public function update(Person $user, Person $person) : bool
    {
        return ($user->id == $person->id);
    }

    /**
     * Determine whether the user can delete the personSchedule.
     *
     * @param Person $user
     * @param Person $person
     * @return bool
     */

    public function delete(Person $user, Person $person) : bool
    {
        return ($user->id == $person->id);
    }
}
