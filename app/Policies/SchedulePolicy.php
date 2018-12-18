<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\Role;

use Illuminate\Auth\Access\HandlesAuthorization;

class SchedulePolicy
{
    use HandlesAuthorization;

    public function before(Person $user) {
        if ($user->hasRole([Role::MANAGE, Role::ADMIN, Role::VC, Role::TRAINER, Role::MENTOR])) {
            return true;
        }
    }

    /**
     * Determine whether the user can view the personSchedule.
     *
     * @param  \App\Models\Person  $user
     * @param  \App\PersonSchedule  $personSchedule
     * @return mixed
     */
    public function view(Person $user, Person $person)
    {
        return ($user->id == $person->id);
    }

    /**
     * Determine whether the user can create personSchedules.
     *
     * @param  \App\Models\Person  $user
     * @return mixed
     */
    public function create(Person $user, Person $person)
    {
        return ($user->id == $person->id);
    }

    /**
     * Determine whether the user can update the personSchedule.
     *
     * @param  \App\Models\Person  $user
     * @param  \App\PersonSchedule  $personSchedule
     * @return mixed
     */
    public function update(Person $user, Person $person)
    {
        return ($user->id == $person->id);
    }

    /**
     * Determine whether the user can delete the personSchedule.
     *
     * @param  \App\Models\Person  $user
     * @param  \App\PersonSchedule  $personSchedule
     * @return mixed
     */
    public function delete(Person $user, Person $person)
    {
        return ($user->id == $person->id);
    }
}
