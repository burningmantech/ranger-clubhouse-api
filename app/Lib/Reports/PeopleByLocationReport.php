<?php

namespace App\Lib\Reports;

use App\Models\Position;
use Illuminate\Support\Facades\DB;

class PeopleByLocationReport
{
    /**
     * Reports on everyone's location.
     *
     * @param int $year
     * @param bool $includeEmail
     * @return array
     */

    public static function execute(int $year, bool $includeEmail): array
    {
        $slotIds = DB::table('slot')
            ->where('begins_year', $year)
            ->where('position_id', '!=', Position::ALPHA)
            ->pluck('id')
            ->toArray();
        $sql = DB::table('person')
            ->select(
                'id',
                'callsign',
                DB::raw('IF(preferred_name != "", preferred_name, first_name) as first_name'),
                'last_name',
                'status',
                'city',
                'state',
                'zip',
                'country',
                DB::raw("EXISTS (SELECT 1 FROM timesheet WHERE person_id=person.id AND YEAR(on_duty)=$year LIMIT 1) as worked"),
            )
            ->orderBy('country')
            ->orderBy('state')
            ->orderBy('city')
            ->orderBy('zip');
        if (empty($slotIds)) {
            $sql->addSelect(DB::raw('false as signed_up'));
        } else {
            $sql->addSelect(DB::raw("EXISTS (SELECT 1 FROM person_slot WHERE person_id=person.id AND slot_id IN (".implode(',', $slotIds).") LIMIT 1) AS signed_up "));
        }
        if ($includeEmail) {
            $sql->addSelect('email');
        }
        return $sql->get()->toArray();
    }

}