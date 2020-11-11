<?php

namespace App\Lib\Reports;

use App\Models\PositionCredit;
use App\Models\Timesheet;

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
        $rows = Timesheet::whereYear('on_duty', $year)
            ->join('position', 'position.id', 'timesheet.position_id')
            ->where('position.count_hours', true)
            ->with(['person:id,callsign,status', 'position:id,title,active'])
            ->orderBy('timesheet.person_id')
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
                $position = $posEntries[0];
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
            usort($positions, function ($a, $b) {
                return strcasecmp($a['title'], $b['title']);
            });

            $results[] = [
                'id' => $personId,
                'callsign' => $person ? $person->callsign : 'Person #' . $personId,
                'status' => $person->status,
                'positions' => $positions,
                'total_duration' => $totalDuration,
                'total_credits' => $totalCredits,
            ];
        }

        usort($results, function ($a, $b) {
            return strcasecmp($a['callsign'], $b['callsign']);
        });

        return $results;
    }
}