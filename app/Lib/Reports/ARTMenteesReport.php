<?php

namespace App\Lib\Reports;

use App\Models\PersonPositionLog;
use App\Models\Position;
use App\Models\Timesheet;
use App\Models\Training;
use Illuminate\Support\Facades\DB;

/**
 * Show who still has ART mentee position(s), when the positions were granted, and the last time they worked said positions.
 */

class ARTMenteesReport
{
    const array EMPTY_RESPONSE = [
        'positions' => [],
        'people' => []
    ];

    public static function execute(Training $training): array
    {
        $info = Position::ART_GRADUATE_TO_POSITIONS[$training->id] ?? null;
        if (!$info || !isset($info['has_mentees'])) {
            return self::EMPTY_RESPONSE;
        }

        $positionIds = $info['positions'] ?? null;
        if (!$positionIds) {
            return self::EMPTY_RESPONSE;
        }

        $positions = DB::table('position')
            ->select('id', 'title')
            ->whereIn('id', $positionIds)
            ->orderBy('title')
            ->get();

        $logs = PersonPositionLog::whereIn('position_id', $positionIds)
            ->with('person:id,callsign,status')
            ->whereNull('left_on')
            ->whereIn('position_id', $positionIds)
            ->get();

        $people = [];
        foreach ($logs as $log) {
            $personId = $log->person_id;
            if (!isset($people[$personId])) {
                $person = $log->person;
                $people[$personId] = [
                    'id' => $person->id,
                    'callsign' => $person->callsign,
                    'status' => $person->status,
                    'positions' => []
                ];
            }

            $timesheet = Timesheet::where('person_id', $personId)
                ->where('position_id', $log->position_id)
                ->orderBy('on_duty', 'desc')
                ->first();

            $people[$personId]['positions'][] = [
                'id' => $log->position_id,
                'granted' => $log->joined_on?->year,
                'last_worked' => $timesheet?->on_duty->year,
            ];
        }

        $people = array_values($people);

        usort($people, fn($a, $b) => strcmp($a['callsign'], $b['callsign']));

        return [
            'positions' => $positions,
            'people' => $people,
        ];
    }
}