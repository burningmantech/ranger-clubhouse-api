<?php

namespace App\Lib\Reports;

use Illuminate\Support\Facades\DB;

class OnlineCourseReport
{
    /**
     * Report on everyone who was enrolled and/or finished the online course
     *
     * @param int $year
     * @param int $positionId
     * @return array
     */

    public static function execute(int $year, int $positionId): array
    {
        $poc = DB::table('person_online_course')
            ->where('year', $year)
            ->where('position_id', $positionId)
            ->get();

        if ($poc->isEmpty()) {
            return [];
        }

        $students = [];

        foreach ($poc as $p) {
            $students[$p->person_id] = [
                'completed_at' => $p->completed_at,
                'enrolled_at' => $p->enrolled_at,
            ];
        }

        $folks = DB::table('person')
            ->select('id', 'callsign', 'status', 'lms_username')
            ->whereIntegerInRaw('id', array_keys($students))
            ->orderBy('callsign')
            ->get();

        return $folks->map(fn($p) => [
            'id' => $p->id,
            'callsign' => $p->callsign,
            'status' => $p->status,
            'lms_username' => $p->lms_username,
            'enrolled_at' => $students[$p->id]['enrolled_at'] ,
            'completed_at' => $students[$p->id]['completed_at'],
        ])->values()->toArray();
    }
}