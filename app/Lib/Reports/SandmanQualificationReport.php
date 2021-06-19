<?php

namespace App\Lib\Reports;

use App\Models\Person;
use App\Models\Position;
use Illuminate\Support\Facades\DB;

class SandmanQualificationReport {
    /**
     * Find everyone who is a Sandman and report on their eligibility to work as a Sandman in the current year.
     *
     * The person must be:
     * - Is an active status account
     * - Granted the Sandman position
     * - Have worked a a burn perimeter within the last SANDMAN_YEAR_CUTOFF years.
     * - Passed Sandman training for the current event
     * - Signed up for a Sandman shift
     *
     * @return array
     */

    public static function execute()
    {
        $year = current_year();
        $cutoff = $year - Position::SANDMAN_YEAR_CUTOFF;

        $positionIds = implode(',', Position::SANDMAN_QUALIFIED_POSITIONS);

        $sandPeople = DB::table('person')
            ->select(
                'id',
                'callsign',
                DB::raw('IFNULL(person_event.sandman_affidavit, FALSE) as sandman_affidavit'),
                DB::raw("EXISTS (SELECT 1 FROM timesheet WHERE timesheet.person_id=person.id AND YEAR(on_duty) >= $cutoff AND position_id IN ($positionIds) LIMIT 1) AS has_experience"),
                DB::raw("EXISTS (SELECT 1 FROM trainee_status JOIN slot ON slot.id=trainee_status.slot_id WHERE trainee_status.person_id=person.id AND slot.position_id=" . Position::SANDMAN_TRAINING . " AND YEAR(slot.begins)=$year AND passed=1 LIMIT 1) as is_trained"),
                DB::raw("EXISTS (SELECT 1 FROM person_slot JOIN slot ON slot.id=person_slot.slot_id WHERE person_slot.person_id=person.id AND slot.position_id=" . Position::SANDMAN . " AND YEAR(slot.begins)=$year LIMIT 1) as is_signed_up")
            )
            ->leftJoin('person_event', function ($j) use ($year) {
                $j->on('person_event.person_id', 'person.id');
                $j->where('year', $year);
            })
            ->where('status', Person::ACTIVE)
            ->whereRaw('EXISTS (SELECT 1 FROM person_position WHERE person_position.person_id=person.id AND person_position.position_id=?)', [Position::SANDMAN])
            ->orderBy('callsign')
            ->get();

        return [
            'sandpeople' => $sandPeople,
            'cutoff_year' => $cutoff
        ];
    }
}