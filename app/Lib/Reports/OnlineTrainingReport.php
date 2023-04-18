<?php

namespace App\Lib\Reports;

use Illuminate\Support\Facades\DB;

class OnlineTrainingReport
{
    /**
     * Report on everyone who was enrolled and/or finished the online training
     *
     * @param int $year
     * @return array
     */

    public static function execute(int $year): array
    {
        $pes = DB::table('person_event')
            ->where('year', $year)
            ->whereNotNull('lms_enrolled_at')
            ->get();

        $ots = DB::table('person_online_training')
            ->whereYear('created_at', $year)
            ->whereYear('completed_at', $year)
            ->get();

        $students = [];

        foreach ($pes as $pe) {
            $students[$pe->person_id] = ['enrolled_at' => $pe->lms_enrolled_at];
        }

        foreach ($ots as $ot) {
            if (!isset($students[$ot->person_id])) {
                $students[$ot->person_id] = [];
            }

            $students[$ot->person_id]['completed_at'] = $ot->completed_at;
        }

        $personIds = array_keys($students);
        if (empty($personIds)) {
            return [];
        }

        $folks = DB::table('person')
            ->select('id', 'callsign', 'status', 'lms_username')
            ->whereIntegerInRaw('id', $personIds)
            ->orderBy('callsign')
            ->get();

        return $folks->map(fn($p) => [
            'id' => $p->id,
            'callsign' => $p->callsign,
            'status' => $p->status,
            'lms_username' => $p->lms_username,
            'enrolled_at' => $students[$p->id]['enrolled_at'] ?? null,
            'completed_at' => $students[$p->id]['completed_at'] ?? null,
        ])->values()->toArray();
    }
}