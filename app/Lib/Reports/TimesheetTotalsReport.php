<?php

namespace App\Lib\Reports;

use App\Models\PositionCredit;
use App\Models\Timesheet;
use Illuminate\Support\Facades\DB;

class TimesheetTotalsReport
{
    /*
      */

    /**
     * Find everyone who worked in a given year, and summarize the positions (total time & credits)
     *
     * @param int $year
     * @return array
     */

    public static function execute(int $year)
    {
        $positionIds = DB::table('position')->where('count_hours', true)->pluck('id');

        $rows = Timesheet::whereYear('on_duty', $year)
            ->whereIn('position_id', $positionIds)
            ->with(['person:id,callsign,status', 'position:id,title,active'])
            ->get();

        if (!$rows->isEmpty()) {
            PositionCredit::warmYearCache($year, array_unique($rows->pluck('position_id')->toArray()));
        }

        $timesheetByPerson = $rows->groupBy('person_id');

        $results = [];

        foreach ($timesheetByPerson as $personId => $entries) {
            $person = $entries[0]->person;

            $group = $entries->groupBy('position_id');
            $positions = [];
            $totalDuration = 0;
            $totalCredits = 0.0;

            // Summarize the positions worked
            foreach ($group as $positionId => $posEntries) {
                $position = $posEntries[0]->position;
                $duration = $posEntries->pluck('duration')->sum();
                $totalDuration += $duration;
                $credits = $posEntries->pluck('credits')->sum();
                $totalCredits += $credits;
                $positions[] = [
                    'id' => $positionId,
                    'title' => $position->title,
                    'active' => $position->active,
                    'duration' => $duration,
                    'credits' => $credits,
                ];
            }

            // Sort by position title
            usort($positions, fn ($a, $b) => strcasecmp($a['title'], $b['title']));


            $results[] = [
                'id' => $personId,
                'callsign' => $person->callsign ?? 'Person #' . $personId,
                'status' => $person->status,
                'positions' => $positions,
                'total_duration' => $totalDuration,
                'total_credits' => $totalCredits,
            ];
        }

        usort($results, fn ($a, $b) => strcasecmp($a['callsign'], $b['callsign']));

        return $results;
    }
}