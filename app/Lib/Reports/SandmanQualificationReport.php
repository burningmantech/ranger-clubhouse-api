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

        $personIds = DB::table('person_position')
            ->join('person', 'person.id', 'person_position.person_id')
            ->where('position_id', Position::SANDMAN)
            ->where('person.status', Person::ACTIVE)
            ->get()
            ->pluck('person_id');

        $trainingIds = DB::table('slot')->whereYear('begins', $year)
                    ->where('position_id', Position::SANDMAN_TRAINING)
                    ->where('active', true)
                    ->get()
                    ->pluck('id');

        $trainedByPersonId = DB::table('trainee_status')
                ->whereIn('slot_id', $trainingIds)
                ->whereIntegerInRaw('person_id', $personIds)
                ->where('passed', true)
                ->get()
                ->keyBy('person_id');

        $sandmanSlotIds = DB::table('slot')
                ->whereYear('begins', $year)
                ->where('position_id', Position::SANDMAN)
                ->get()
                ->pluck('id');

        $sandmanShiftByPersonId = DB::table('person_slot')
                ->whereIn('slot_id', $sandmanSlotIds)
                ->get()
                ->keyBy('person_id');

        $pastWork = DB::table('timesheet')
                ->select('person_id')
                ->whereYear('on_duty', '>=', $cutoff)
                ->whereIn('position_id', Position::SANDMAN_QUALIFIED_POSITIONS)
                ->whereIntegerInRaw('person_id', $personIds)
                ->groupBy('person_id')
                ->get()
                ->keyBy('person_id');

        $sandPeople = DB::table('person')
            ->select(
                'id',
                'callsign',
                DB::raw('IFNULL(person_event.sandman_affidavit, FALSE) as sandman_affidavit'),
            )->leftJoin('person_event', function ($j) use ($year) {
                $j->on('person_event.person_id', 'person.id');
                $j->where('year', $year);
            })
            ->whereIntegerInRaw('person.id', $personIds)
            ->orderBy('callsign')
            ->get();

        foreach ($sandPeople as $person) {
            $id = $person->id;
            $person->is_trained = $trainedByPersonId->has($id);
            $person->is_signed_up = $sandmanShiftByPersonId->has($id);
            $person->has_experience = $pastWork->has($id);
        }

        return [
            'sandpeople' => $sandPeople,
            'cutoff_year' => $cutoff
        ];
    }
}