<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\Timesheet;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class TimesheetPolicy
{
    use HandlesAuthorization;

    public function before(Person $user) {
        if ($user->hasRole([Role::TIMESHEET_MANAGEMENT, Role::ADMIN])) {
            return true;
        }
    }
    /**
     * Determine whether the user can view the timesheet.
     *
     * @param  \App\Models\Person  $user
     * @param  \App\Timesheet  $timesheet
     * @return mixed
     */
    public function index(Person $user, $personId)
    {
        return ($user->id == $personId);
    }

    /**
     * Determine whether the user can create timesheets.
     *
     * @param  \App\Models\Person  $user
     * @return mixed
     */
    public function create(Person $user)
    {
        //
    }

    /**
     * Determine whether the user can update the timesheet.
     *
     * @param  \App\Models\Person  $user
     * @param  \App\Timesheet  $timesheet
     * @return mixed
     */
    public function update(Person $user, Timesheet $timesheet)
    {
        //
    }

    /**
     * Determine whether the user can delete the timesheet.
     *
     * @param  \App\Models\Person  $user
     * @param  \App\Timesheet  $timesheet
     * @return mixed
     */
    public function delete(Person $user, Timesheet $timesheet)
    {
        //
    }
}
