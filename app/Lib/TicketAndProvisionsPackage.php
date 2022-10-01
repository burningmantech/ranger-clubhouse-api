<?php

namespace App\Lib;

use App\Models\AccessDocument;
use App\Models\Provision;
use App\Models\Timesheet;

class TicketAndProvisionsPackage
{
    /**
     * Build up a ticketing package for the person
     *
     * @param int $personId
     * @return array
     */

    public static function buildPackageForPerson(int $personId): array
    {
        $year = event_year() - 1;
        if ($year == 2020 || $year == 2021) {
            // 2020 & 2021 didn't happen. :-(
            $year = 2019;
        }

        return [
            'access_documents' => AccessDocument::findForQuery(['person_id' => $personId]),
            'provisions' =>  Provision::findForQuery(['person_id' => $personId]),
            'credits_earned' => Timesheet::earnedCreditsForYear($personId, $year),
            'year_earned' => $year,
            'period' => setting('TicketingPeriod')
        ];
    }

}