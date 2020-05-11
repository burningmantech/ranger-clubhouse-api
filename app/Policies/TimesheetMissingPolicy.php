<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\TimesheetMissing;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class TimesheetMissingPolicy
{
    use HandlesAuthorization;


    private function hasRoles(Person $user) {
        if ($user->hasRole([Role::TIMESHEET_MANAGEMENT, Role::MANAGE, Role::ADMIN])) {
            return true;
        }
    }

    /*
     * Determine whether the user can view the timesheet(s).
     */
    public function view(Person $user, $personId)
    {
        return ($this->hasRoles($user) || $user->id == $personId);
    }

    /*
     * Can the user see all the timesheet missing requests in the database
     */

    public function viewAll(Person $user) {
        return $this->hasRole([ Role::ADMIN, Role::TIMESHEET_MANAGEMENT ]);
    }

    /*
     * Can the user create this timesheet missing request?
     */

    public function store(Person $user, TimesheetMissing $timesheetMissing)
    {
        return ($this->hasRoles($user) ||$user->id == $timesheetMissing->person_id);
    }

    /*
     * Can the user update this row?
     */

    public function update(Person $user, TimesheetMissing $timesheetMissing)
    {
        return ($this->hasRoles($user) ||$user->id == $timesheetMissing->person_id);
    }

    /*
     * Can the user delete the row?
     */

    public function destroy(Person $user, TimesheetMissing $timesheetMissing)
    {
        return ($this->hasRoles($user) ||$user->id == $timesheetMissing->person_id);
    }

}
