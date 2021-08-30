<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\Position;
use App\Models\Timesheet;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class TimesheetPolicy
{
    use HandlesAuthorization;

    public function before(Person $user)
    {
        if ($user->hasRole([Role::TIMESHEET_MANAGEMENT, Role::ADMIN])) {
            return true;
        }
    }

    /*
     * Determine whether the user can view the timesheet.
     */
    public function index(Person $user, $personId)
    {
        return $user->hasRole(Role::MANAGE) || ($user->id == $personId);
    }

    /*
     * can the user create a timesheet
     */

    public function store(Person $user, Timesheet $timesheet)
    {
        return false;
    }

    /*
     * Can the user update a timesheet?
     */

    public function update(Person $user, Timesheet $timesheet)
    {
        return $user->hasRole(Role::MANAGE) || ($user->id == $timesheet->person_id);
    }

    /**
     * Can the user update the position on an active timesheet?
     */

    public function updatePosition(Person $user, Timesheet $timesheet)
    {
        return $user->hasRole(Role::MANAGE);
    }

    /*
     * Can a user confirm the timesheet?
     */

    public function confirm(Person $user, $personId)
    {
        return $user->hasRole(Role::MANAGE) || ($user->id == $personId);
    }

    /*
     * Can a user delete a timesheet? Only timesheet manager,
     * or admin, covered in before()
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
        return $user->hasRole(Role::MANAGE);
    }

    /*
     * Can user signoff the timesheet?
     */

    public function signoff(Person $user, Timesheet $timesheet)
    {
        return $user->hasRole(Role::MANAGE);
    }

    /*
     * Can user see a timesheet log?
     */

    public function log(Person $user, $id)
    {
        return $user->hasRole(Role::MANAGE);
    }

    /*
     * Can the user see the timesheet correction requests?
     */

    public function correctionRequests(Person $user)
    {
        return $user->hasRole(Role::MANAGE);
    }

    /*
     * Can the user see the timesheet unconfirmed people?
     */

    public function unconfirmedPeople(Person $user)
    {
        return $user->hasRole(Role::MANAGE);
    }

    /*
     * Can the user see the timesheet unconfirmed people?
     */

    public function sanityChecker(Person $user)
    {
        return $user->hasRole([Role::ADMIN, Role::TIMESHEET_MANAGEMENT]);
    }

    /**
     * Can the user run a freaking years report?
     */

    public function freakingYearsReport(Person $user)
    {
        return $user->hasRole(Role::MANAGE);
    }

    /**
     * Can the user run a freaking years report?
     */

    public function shirtsEarnedReport(Person $user)
    {
        return $user->hasRole(Role::MANAGE);
    }

    /**
     * Can the user run a potential shirts earned report?
     */

    public function potentialShirtsEarnedReport(Person $user)
    {
        return $user->hasRole(Role::MANAGE);
    }

    /**
     * Can the user run a radio eligibility report?
     */

    public function radioEligibilityReport(Person $user)
    {
        return $user->hasRole(Role::MANAGE);
    }

    /**
     * Can the user bulk sign in and/or out people?
     */

    public function bulkSignInOut(Person $user)
    {
        return false;
    }

    /**
     * Can the user run the hours/credit report
     */

    public function hoursCreditsReport(Person $user)
    {
        return $user->hasRole(Role::MANAGE);
    }

    /**
     * Can the user run the Special Teams report
     */

    public function specialTeamsReport(Person $user)
    {
        return $user->hasRole(Role::MANAGE);
    }

    /**
     * Can the user run the Thank You cards report
     */

    public function thankYou(Person $user)
    {
        return $user->hasRole(Role::MANAGE);
    }

    /*
     * Can the user run the Timesheet By Callsign report
     */

    public function timesheetByCallsign(Person $user)
    {
        return $user->hasRole(Role::MANAGE);
    }

    /*
     * Can the user run the Timesheet Totals report
     */

    public function timesheetTotals(Person $user)
    {
        return $user->hasRole(Role::MANAGE);
    }

    /*
     * Can the user run the Timesheet By Position Report
     */

    public function timesheetByPosition(Person $user)
    {
        return $user->hasRole(Role::MANAGE);
    }

    /**
     * Can the person run the On Duty Shift Lead Report
     *
     * @param Person $user
     * @return bool
     */
    public function onDutyShiftLeadReport(Person $user) {
        return $user->hasRole(Role::MANAGE);
    }
}
