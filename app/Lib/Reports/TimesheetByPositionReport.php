<?php

namespace App\Lib\Reports;

use App\Models\PositionCredit;
use App\Models\Timesheet;
use Illuminate\Support\Facades\DB;
use Psr\SimpleCache\InvalidArgumentException;

class TimesheetByPositionReport
{
    /**
     * Breakdown the positions within a given year
     *
     * @param int $year
     * @param bool $includeEmail
     * @return array
     * @throws InvalidArgumentException
     */

    public static function execute(int $year, bool $includeEmail = false): array
    {
        $now = now();
        $rows = Timesheet::whereYear('on_duty', $year)
            ->select(
                '*',
                DB::raw("(UNIX_TIMESTAMP(IFNULL(off_duty, '$now')) - UNIX_TIMESTAMP(on_duty)) AS duration")
            )->with(['person:id,callsign,status,email', 'position:id,title,active'])
            ->orderBy('on_duty')
            ->get()
            ->groupBy('position_id');

        $positions = [];
        $people = [];

        if ($rows->isNotEmpty()) {
            PositionCredit::warmBulkYearCache([$year => $rows->keys() ]);
        }

        foreach ($rows as $positionId => $entries) {
            $position = $entries[0]->position;
            $positions[] = [
                'id' => $position->id,
                'title' => $position->title,
                'active' => $position->active,
                'timesheets' => $entries->map(function ($r) use ($includeEmail, & $people) {
                    $person = $r->person;
                    $people[$r->person_id] ??= [
                        'id' => $r->person_id,
                        'callsign' => $person->callsign ?? 'Person #' . $r->person_id,
                        'status' => $person->status ?? 'deleted'
                    ];

                    if ($includeEmail) {
                        $people[$r->person_id]['email'] ??= $person->email ?? '';
                    }

                    return [
                        'id' => $r->id,
                        'on_duty' => (string)$r->on_duty,
                        'off_duty' => (string)$r->off_duty,
                        'duration' => $r->duration,
                        'person_id' => $r->person_id,
                        'credits' => $r->credits,
                    ];
                })
            ];
        }

        usort($positions, fn($a, $b) => strcasecmp($a['title'], $b['title']));

        return [
            'positions' => $positions,
            'people' => $people,
        ];
    }
}