<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\Timesheet;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class TimesheetPolicy
{
    use HandlesAuthorization;

    public function before(Person $user)
    {
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
     * can the user create a timesheet
     */

    public function store(Person $user, Timesheet $timesheet)
    {
        return false;
    }

    /*
     * Can the user mark the sheet as verified?
     */

    public function update(Person $user, Timesheet $timesheet)
    {
        return ($user->id == $timesheet->person_id);
    }

    /*
     * Can a user confirm the timesheet?
     */

    public function confirm(Person $user, $personId)
    {
        return ($user->id == $personId);
    }

    /*
     * Can a user delete a timesheet? Only timesheet manager,
     * login manage, or admin, covered in before()
     */

    public function destroy(Person $user, Timesheet $timesheet)
    {
        return false;
    }

    /*
     * Can user signin the person?
     */

    public function signin(Person $user)
    {
        return false;
    }

    /*
     * Can user signoff the timesheet?
     */

    public function signoff(Person $user, Timesheet $timesheet)
    {
        return false;
    }

    /*
     * Can user see a timesheet log?
     */

    public function log(Person $user, $id)
    {
        return false;
    }

    /*
     * Can the user see the timesheet correction requests?
     */

    public function correctionRequests(Person $user)
    {
        return false;
    }

    /*
     * Can the user see the timesheet unconfirmed people?
     */

    public function unconfirmedPeople(Person $user)
    {
        return false;
    }

    /**
     * Can the user run a freaking years report?
     */

    public function freakingYearsReport(Person $user)
    {
        return false;
    }

    /**
     * Can the user run a freaking years report?
     */

    public function shirtsEarnedReport(Person $user)
    {
        return false;
    }

    /**
     * Can the user run a radio eligibility report?
     */

    public function radioEligibilityReport(Person $user)
    {
        return false;
    }

    /**
     * Can the user bulk sign in and/or out people?
     */

    public function bulkSignInOut(Person $user)
    {
        return false;
    }
}
