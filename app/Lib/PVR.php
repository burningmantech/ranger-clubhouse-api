<?php

namespace App\Lib;

use App\Models\PersonEvent;
use App\Models\PersonSlot;
use App\Models\PersonTeam;

class PVR
{
    /**
     * Is the person Personal Vehicle eligible?
     *
     * @param int $personId
     * @param PersonEvent|null $event
     * @param int $year
     * @return bool
     */

    public static function isEligible(int $personId, ?PersonEvent $event, int $year): bool
    {
        if ($event?->may_request_stickers) {
            return true;
        }

        return PersonTeam::havePVREligibleTeam($personId) || PersonSlot::hasPVREligibleSignups($personId, $year);
    }
}