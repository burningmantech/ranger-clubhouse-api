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

        $positionGrants = DB::table('person_position')
            ->whereIn('position_id', $positionIds)
            ->get();

        if ($positionGrants->isEmpty()) {
            return self::EMPTY_RESPONSE;
        }

        $grantIds = $positionGrants->pluck('person_id')->unique();
        $positionGrants = $positionGrants->groupBy('person_id');

        $people = DB::table('person')
            ->select('id', 'callsign', 'status')
            ->whereIn('id', $grantIds)
            ->distinct()
            ->orderBy('person.callsign')
            ->get();

        $grantLogs = PersonPositionLog::whereIn('position_id', $positionIds)
            ->whereIn('person_id', $grantIds)
            ->whereNull('left_on')
            ->get()
            ->groupBy('person_id');

        $mentees = [];
        foreach ($people as $person) {
            $mentee = [
                'id' => $person->id,
                'callsign' => $person->callsign,
                'status' => $person->status,
                'positions' => []
            ];

            foreach ($positionIds as $pid) {
                $timesheet = Timesheet::where('person_id', $person->id)
                    ->select('on_duty')
                    ->where('position_id', $pid)
                    ->orderBy('on_duty', 'desc')
                    ->first();

                $foundLog = $grantLogs->get($person->id)?->firstWhere('position_id', $pid);
                $granted = $positionGrants->get($person->id);
                $mentee['positions'][] = [
                    'id' => $pid,
                    'is_granted' => $granted?->contains(fn ($p) => $p->position_id == $pid),
                    'granted' => $foundLog?->joined_on?->year,
                    'last_worked' => $timesheet?->on_duty->year,
                ];
            }

            $mentees[] = $mentee;
        }

        return [
            'positions' => $positions,
            'people' => $mentees,
        ];
    }
}