<?php

namespace App\Lib\Reports;

use App\Models\PositionCredit;
use App\Models\Timesheet;
use Illuminate\Support\Facades\DB;

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
        $now = now();
        $rows = Timesheet::whereYear('on_duty', $year)
            ->select([
                '*',
                DB::raw("(UNIX_TIMESTAMP(IFNULL(off_duty, '$now')) - UNIX_TIMESTAMP(on_duty)) AS duration"),
            ])
            ->with(['person:id,callsign,status', 'position:id,title,type,count_hours,active'])
            ->orderBy('on_duty')
            ->get();

        if (!$rows->isEmpty()) {
            PositionCredit::warmYearCache($year, array_unique($rows->pluck('position_id')->toArray()));
        }

        $personGroups = $rows->groupBy('person_id');

        $callsigns = $personGroups->map(function ($group) {
            $person = $group[0]->person;

            return [
                'id' => $group[0]->person_id,
                'callsign' => $person->callsign ?? "Person #" . $group[0]->person_id,
                'status' => $person->status ?? 'deleted',

                'total_credits' => $group->pluck('credits')->sum(),
                'total_duration' => $group->pluck('duration')->sum(),
                'total_appreciation_duration' => $group->filter(function ($t) {
                    return $t->position ? $t->position->count_hours : false;
                })->pluck('duration')->sum(),

                'timesheet' => $group->map(function ($t) {
                    return [
                        'id' => $t->id,
                        'position_id' => $t->position_id,
                        'on_duty' => (string)$t->on_duty,
                        'off_duty' => (string)$t->off_duty,
                        'duration' => $t->duration,
                        'credits' => $t->credits,
                    ];
                })->values()
            ];
        })->sortBy('callsign', SORT_NATURAL | SORT_FLAG_CASE)->values()->toArray();

        $positions = [];
        foreach ($rows as $row) {
            $position = $row->position;
            $positions[$row->position_id] ??= [
                'id' => $row->position_id,
                'title' => $position->title ?? "Position #" . $row->position_id,
                'count_hours' => $position->count_hours ?? 0,
                'active' => $position->active ?? false,
            ];
        }

        return [
            'people' => $callsigns,
            'positions' => $positions,
        ];
    }
}