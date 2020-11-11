<?php

namespace App\Lib\Reports;

use App\Models\Timesheet;

class TimesheetByPositionReport
{
    /**
     * Breakdown the positions within a given year
     *
     * @param int $year
     * @param bool $includeEmail
     * @return array
     */

    public static function execute(int $year, bool $includeEmail = false)
    {
        $rows = Timesheet::whereYear('on_duty', $year)
            ->with(['person:id,callsign,status,email', 'position:id,title,active'])
            ->orderBy('on_duty')
            ->get()
            ->groupBy('position_id');

        $results = [];

        foreach ($rows as $positionId => $entries) {
            $position = $entries[0]->position;
            $results[] = [
                'id' => $position->id,
                'title' => $position->title,
                'active' => $position->active,
                'timesheets' => $entries->map(function ($r) use ($includeEmail) {
                    $person = $r->person;
                    $personInfo = [
                        'id' => $r->person_id,
                        'callsign' => $person ? $person->callsign : 'Person #' . $r->person_id,
                        'status' => $person ? $person->status : 'deleted'
                    ];

                    if ($includeEmail) {
                        $personInfo['email'] = $person ? $person->email : '';
                    }

                    return [
                        'id' => $r->id,
                        'on_duty' => (string)$r->on_duty,
                        'off_duty' => (string)$r->off_duty,
                        'duration' => $r->duration,
                        'person' => $personInfo
                    ];
                })
            ];
        }

        usort($results, function ($a, $b) {
            return strcasecmp($a['title'], $b['title']);
        });

        return $results;
    }
}