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
        if ($user->hasRole([Role::TIMESHEET_MANAGEMENT, Role::MANAGE, Role::ADMIN])) {
            return true;
        }
    }

    /*
     * Determine whether the user can view the timesheet.
     */
    public function index(Person $user, $personId)
    {
        return ($user->id == $personId);
    }

    /*
     * Can the user mark the sheet as verified?
     */

    public function update(Person $user, $personId)
    {
        return ($user->id == $personId);
    }

    /*
     * Can a user confirm the timesheet?
     */

    public function confirm(Person $user, $personId)
    {
        return ($user->id == $personId);
    }
}
