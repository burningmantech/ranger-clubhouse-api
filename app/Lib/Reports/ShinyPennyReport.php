<?php

namespace App\Lib\Reports;

use App\Models\Person;
use App\Models\Position;
use Illuminate\Support\Facades\DB;

class ShinyPennyReport
{
    /**
     * Find all Shiny Pennies in a given year
     *
     * @param int $year
     * @return array
     */

    public static function execute(int $year): array
    {
        $pennyIds = DB::table('person_status')
            ->select('person_id')
            ->whereYear('created_at', $year)
            ->where('old_status', Person::ALPHA)
            ->where('new_status', Person::ACTIVE)
            ->whereRaw('EXISTS (SELECT 1 FROM timesheet WHERE timesheet.person_id=person_status.person_id AND timesheet.position_id=? AND YEAR(timesheet.on_duty)=? LIMIT 1)', [Position::ALPHA, $year])
            ->groupBy('person_id')
            ->get()
            ->pluck('person_id')
            ->toArray();

        if (empty($pennyIds)) {
            return [];
        }

        return DB::table('person')
            ->select(
                'id',
                'callsign',
                'email',
                'status',
                DB::raw('IF(preferred_name != "", preferred_name, first_name) as first_name'),
                'last_name'
            )->whereIntegerInRaw('id', $pennyIds)
            ->orderBy('callsign')
            ->get()
            ->toArray();
    }
}