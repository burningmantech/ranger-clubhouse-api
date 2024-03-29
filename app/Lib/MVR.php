<?php

namespace App\Lib;

use App\Models\PersonEvent;
use App\Models\PersonSlot;
use App\Models\PersonTeam;

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
}