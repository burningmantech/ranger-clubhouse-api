<?php

namespace App\Lib;

use App\Models\PersonEvent;
use App\Models\PersonPosition;
use App\Models\PersonTeam;

class MVR
{
    /**
     * Check if the person is MVR eligible, and report on what teams and/or positions the person is
     * eligible through.
     *
     * @param int $personId
     * @param PersonEvent|null $event
     * @param array $teams
     * @param array $positions
     * @return bool
     */
    public static function eligibleInfo(int $personId, ?PersonEvent $event, array &$teams, array &$positions): bool
    {
        $teams = PersonTeam::retrieveMVREligibleForPerson($personId);
        $positions = PersonPosition::retrieveMVREligibleForPerson($personId);

       return $event?->mvr_eligible || count($teams) || count($positions);
    }

    /**
     * Is the person MVR eligible?
     *
     * @param int $personId
     * @param PersonEvent|null $event
     * @return bool
     */

    public static function isEligible(int $personId, ?PersonEvent $event): bool
    {
        if ($event?->mvr_eligible) {
            return true;
        }

       return PersonTeam::haveMvrEligibleForPerson($personId) || PersonPosition::haveMvrEligibleForPerson($personId);
    }
}