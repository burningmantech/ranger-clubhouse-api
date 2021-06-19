<?php

namespace App\Lib\Reports;

use App\Models\Position;
use Illuminate\Support\Facades\DB;

class PeopleByLocationReport
{
    /**
     * Reports on everyone's location.
     *
     * @param $year
     * @return array
     */
    public static function execute($year): array
    {
        return DB::table('person')
            ->select(
                'id',
                'callsign',
                'first_name',
                'last_name',
                'status',
                'email',
                'city',
                'state',
                'zip',
                'country',
                DB::raw("EXISTS (SELECT 1 FROM timesheet WHERE person_id=person.id AND YEAR(on_duty)=$year LIMIT 1) as worked"),
                DB::raw("EXISTS (SELECT 1 FROM person_slot JOIN slot ON slot.id=person_slot.slot_id AND YEAR(slot.begins)=$year AND slot.position_id != " . Position::ALPHA . " WHERE person_slot.person_id=person.id LIMIT 1) AS signed_up ")
            )
            ->orderBy('country')
            ->orderBy('state')
            ->orderBy('city')
            ->orderBy('zip')
            ->get()
            ->toArray();
    }

}