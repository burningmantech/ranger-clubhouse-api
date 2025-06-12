<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\Role;
use App\Models\Timesheet;
use Illuminate\Auth\Access\HandlesAuthorization;

class TimesheetPolicy
{
    use HandlesAuthorization;

    public function before(Person $user): ?true
    {
        if ($user->hasRole([Role::TIMESHEET_MANAGEMENT, Role::ADMIN])) {
            return true;
        }

        return null;
    }

    /*
     * Determine whether the user can view the timesheet.
     */

    public function index(Person $user, $personId): bool
    {
        return $user->hasRole(Role::EVENT_MANAGEMENT) || ($user->id == $personId);
    }

    /*
     * can the user create a timesheet
     */

    public function store(Person $user, Timesheet $timesheet): bool
    {
        return false;
    }

    /*
     * Can the user update a timesheet?
     */

    public function update(Person $user, Timesheet $timesheet): bool
    {
        return $user->hasRole(Role::SHIFT_MANAGEMENT) || ($user->id == $timesheet->person_id);
    }

    /**
     * Can the user update the position on an active timesheet?
     */

    public function updatePosition(Person $user, Timesheet $timesheet): bool
    {
        return $user->hasRole(Role::SHIFT_MANAGEMENT) || ($user->hasRole(Role::SHIFT_MANAGEMENT_SELF) && $user->id == $timesheet->person_id);
    }

    /*
     * Can a user confirm the timesheet?
     */

    public function confirm(Person $user, $personId): bool
    {
        return $user->hasRole(Role::TECH_NINJA) || ($user->id == $personId);
    }

    /**
     * Can a user delete a timesheet?
     */

    public function destroy(Person $user, Timesheet $timesheet): bool
    {
        // Allow a timesheet to be deleted if the duration is too short - i.e., accidental shift start.
        if (($user->hasRole(Role::SHIFT_MANAGEMENT) || ($user->hasRole(Role::SHIFT_MANAGEMENT_SELF) && $timesheet->person_id == $user->id))
            && $timesheet->duration < Timesheet::TOO_SHORT_LENGTH) {
            return true;
        }
        return false;
    }

    /**
     * Can a user delete a timesheet? Only timesheet manager,
     * or admin, covered in before()
     *
     * @param Person $user
     * @param Timesheet $timesheet
     * @return bool
     */

    public function deleteMistake(Person $user, Timesheet $timesheet): bool
    {
        return $user->hasRole(Role::SHIFT_MANAGEMENT) || ($user->hasRole(Role::SHIFT_MANAGEMENT_SELF) && $timesheet->person_id == $user->id);
    }

    /**
     * Can user sign in the person?
     */

    public function signin(Person $user, int $personId): bool
    {
        if ($user->hasRole(Role::SHIFT_MANAGEMENT)) {
            return true;
        }

        return $user->hasRole(Role::SHIFT_MANAGEMENT_SELF) && $user->id == $personId;
    }

    /**
     * Can user re-sign in the person?
     */

    public function resignin(Person $user): bool
    {
        return $user->hasRole(Role::SHIFT_MANAGEMENT);
    }

    /**
     * Can user signoff the timesheet?
     */

    public function signoff(Person $user, Timesheet $timesheet): bool
    {
        return $user->hasRole([Role::SHIFT_MANAGEMENT, Role::SHIFT_MANAGEMENT_SELF]);
    }

    /**
     * Can user see a timesheet log?
     */

    public function log(Person $user, $id): bool
    {
        return $user->hasRole(Role::EVENT_MANAGEMENT);
    }

    /**
     * Can the user see the timesheet correction requests?
     */

    public function correctionRequests(Person $user): bool
    {
        return $user->hasRole(Role::TIMESHEET_MANAGEMENT);
    }

    /**
     * Can the user see the timesheet unconfirmed people?
     */

    public function unconfirmedPeople(Person $user): bool
    {
        return $user->hasRole(Role::TIMESHEET_MANAGEMENT);
    }

    /**
     * Can the user see the timesheet unconfirmed people?
     */

    public function sanityChecker(Person $user): bool
    {
        return $user->hasRole([Role::ADMIN, Role::TIMESHEET_MANAGEMENT]);
    }

    /**
     * Can the user run a freaking years report?
     */

    public function freakingYearsReport(Person $user): bool
    {
        return $user->hasRole(Role::QUARTERMASTER);
    }

    /**
     * Can the user run a potential shirts earned report?
     */

    public function potentialShirtsEarnedReport(Person $user): bool
    {
        return $user->hasRole(Role::QUARTERMASTER);
    }

    /**
     * Can the user run a radio eligibility report?
     */

    public function radioEligibilityReport(Person $user): bool
    {
        return false;
    }

    /**
     * Can the user bulk sign in and/or out people?
     */

    public function bulkSignInOut(Person $user): bool
    {
        return $user->hasRole(Role::TIMESHEET_MANAGEMENT);
    }

    /**
     * Can the user run the hours/credit report
     */

    public function hoursCreditsReport(Person $user): bool
    {
        return false;
    }

    /**
     * Can the user run the Special Teams report
     */

    public function specialTeamsReport(Person $user): bool
    {
        return $user->hasRole(Role::EVENT_MANAGEMENT);
    }

    /**
     * Can the user run the Thank You cards report
     */

    public function thankYou(Person $user): bool
    {
        return false;
    }

    /*
     * Can the user run the Timesheet By Callsign report
     */

    public function timesheetByCallsign(Person $user): bool
    {
        return $user->hasRole(Role::EVENT_MANAGEMENT);
    }

    /*
     * Can the user run the Timesheet Totals report
     */

    public function timesheetTotals(Person $user): bool
    {
        return false;
    }

    /*
     * Can the user run the Timesheet By Position Report
     */

    public function timesheetByPosition(Person $user): bool
    {
        return $user->hasRole(Role::EVENT_MANAGEMENT);
    }

    /**
     * Can the person run the On Duty Shift Lead Report
     *
     * @param Person $user
     * @return bool
     */
    public function onDutyShiftLeadReport(Person $user): bool
    {
        return $user->hasRole(Role::EVENT_MANAGEMENT);
    }

    public function onDutyReport(Person $user): bool
    {
        return $user->hasRole(Role::EVENT_MANAGEMENT);
    }

    public function retentionReport(Person $user): bool
    {
        return false;
    }

    public function topHourEarnersReport(Person $user): bool
    {
        return false;
    }

    public function repairSlotAssociations(Person $user): bool
    {
        return false;
    }

    public function eventStatsReport(Person $user): bool
    {
        return $user->hasRole(Role::EVENT_MANAGEMENT);
    }

    public function shiftDropReport(Person $user): bool
    {
        return $user->hasRole(Role::EVENT_MANAGEMENT);
    }

    public function forcedSigninsReport(Person $user): bool
    {
        return $user->hasRole(Role::EVENT_MANAGEMENT);
    }

    public function noShowsReport(Person $user): bool
    {
        return $user->hasRole(Role::EVENT_MANAGEMENT);
    }

    public function correctionStatistics(Person $user): false
    {
        return false;
    }

    public function checkForOverlaps(Person $user, $personId): bool
    {
        return $user->hasRole(Role::EVENT_MANAGEMENT) || $personId == $user->id;
    }

    public function earlyLateCheckins(Person $user): bool
    {
        return $user->hasRole(Role::TIMESHEET_MANAGEMENT);
    }
}
