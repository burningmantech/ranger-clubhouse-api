<?php

namespace App\Lib\Reports;

use App\Models\Position;
use Illuminate\Support\Facades\DB;

class TrainingSlotCapacityReport
{

    /*
     * Find the signups for all training shifts in a given year
     *
     * The method returns:
     *
     * slot_id: slot record id
     * description: slot description
     * date: starts on datetime
     * max: sign up limit
     * signed_up: total signup count
     * filled: how full in a percentage (0 to 100+)
     * alpha_count: alpha & prospective count
     * veteran_count: signups who are not alpha, auditor or prospective
     * auditor_count: signups who are auditors
     *
     * @param int $year the year to search
     */

    public static function execute($position, int $year)
    {
        $positionIds = [$position->id];
        if ($position->id == Position::HQ_FULL_TRAINING) {
            $positionIds[] = Position::HQ_REFRESHER_TRAINING;
        }

        $rows = DB::table('slot')
            ->select(
                'slot.id as slot_id',
                'slot.description',
                'slot.begins as date',
                'slot.max',
                DB::raw('(
                SELECT COUNT(person.id) FROM person_slot
                LEFT JOIN person ON person.id=person_slot.person_id
                WHERE person_slot.slot_id=slot.id) as signed_up'),
                DB::raw('(
                SELECT COUNT(person.id) FROM person_slot
                LEFT JOIN person ON person.id=person_slot.person_id AND person.status in ("alpha", "prospective")
                WHERE person_slot.slot_id=slot.id) as alpha_count'),
                DB::raw('(
                SELECT COUNT(person.id) FROM person_slot
                LEFT JOIN person ON person.id=person_slot.person_id AND person.status NOT IN ("alpha", "auditor", "prospective")
                WHERE person_slot.slot_id=slot.id) as veteran_count'),
                DB::raw('(
                SELECT COUNT(person.id) FROM person_slot LEFT JOIN person ON person.id=person_slot.person_id AND person.status="auditor"
                WHERE person_slot.slot_id=slot.id) as auditor_count')
            )
            ->whereYear('slot.begins', $year)
            ->whereIn('slot.position_id', $positionIds)
            ->orderBy('slot.begins')->get();

        foreach ($rows as $row) {
            if ($row->signed_up > 0 && $row->max > 0) {
                $row->filled = round(($row->signed_up / $row->max) * 100);
            } else {
                $row->filled = 0;
            }
        }

        return $rows;
    }
}