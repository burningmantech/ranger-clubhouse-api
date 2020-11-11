<?php

namespace App\Lib\Reports;

use App\Models\PositionCredit;
use App\Models\Timesheet;

class TimesheetByCallsignReport
{
    /**
     * Retrieve all timesheets for a given year, grouped by callsign
     *
     * @param int $year
     * @return array
     */
    public static function execute(int $year)
    {
        $rows = Timesheet::whereYear('on_duty', $year)
            ->with(['person:id,callsign,status', 'position:id,title,type,count_hours,active'])
            ->orderBy('on_duty')
            ->get();

        if (!$rows->isEmpty()) {
            PositionCredit::warmYearCache($year, array_unique($rows->pluck('position_id')->toArray()));
        }

        $personGroups = $rows->groupBy('person_id');

        return $personGroups->map(function ($group) {
            $person = $group[0]->person;

            return [
                'id' => $group[0]->person_id,
                'callsign' => $person ? $person->callsign : "Person #" . $group[0]->person_id,
                'status' => $person ? $person->status : 'deleted',

                'total_credits' => $group->pluck('credits')->sum(),
                'total_duration' => $group->pluck('duration')->sum(),
                'total_appreciation_duration' => $group->filter(function ($t) {
                    return $t->position ? $t->position->count_hours : false;
                })->pluck('duration')->sum(),

                'timesheet' => $group->map(function ($t) {
                    return [
                        'on_duty' => (string)$t->on_duty,
                        'off_duty' => (string)$t->off_duty,
                        'duration' => $t->duration,
                        'credits' => $t->credits,
                        'position' => [
                            'id' => $t->position_id,
                            'title' => $t->position ? $t->position->title : "Position #" . $t->position_id,
                            'count_hours' => $t->position ? $t->position->count_hours : 0,
                            'active' => $t->position ? $t->position->active : false,
                        ]
                    ];
                })->values()
            ];
        })->sortBy('callsign', SORT_NATURAL | SORT_FLAG_CASE)->values()->toArray();
    }
}