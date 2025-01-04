<?php

namespace App\Lib;

use App\Models\PersonEvent;
use App\Models\PersonSlot;
use App\Models\PersonTeam;
use Carbon\Carbon;

class MVR
{
    /**
     * Is the person MVR eligible?
     *
     * @param int $personId
     * @param PersonEvent|null $event
     * @param int $year
     * @return bool
     */

    public static function isEligible(int $personId, ?PersonEvent $event, int $year): bool
    {
        if ($event?->mvr_eligible) {
            return true;
        }

        return PersonTeam::haveMvrEligibleForPerson($personId) || PersonSlot::hasMVREligibleSignups($personId, $year);
    }

    /**
     * Return the MVR submission deadline, and has it past?
     *
     * @return array
     */

    public static function retrieveDeadline() :array {
        $deadline = current_year()."-" . setting('MVRDeadline') . " 23:59:59";
        return [$deadline , now()->gt(Carbon::parse($deadline))];
    }
}