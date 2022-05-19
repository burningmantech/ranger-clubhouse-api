<?php

namespace App\Lib\Reports;

use App\Models\Person;
use App\Models\Position;
use Illuminate\Support\Facades\DB;

class RadioEligibilityReport
{
    /**
     * Radio Eligibility
     *
     * Takes the current year, and figures out:
     * - How many hours worked (excluding Alpha & Training shifts) in the previous two years
     * - If the person is signed up to work in the current year.
     *
     * @param int $currentYear
     * @return array
     */

    public static function execute(int $currentYear): array
    {
        // 2020 & 2021 didn't happen, so adjust for that.
        if ($currentYear == 2021 || $currentYear == 2022) {
            $lastYear = 2019;
            $prevYear = 2018;
        } else if ($currentYear == 2023) {
            $lastYear = 2022;
            $prevYear = 2019;
        } else {
            $lastYear = $currentYear - 1;
            $prevYear = $currentYear - 2;
        }

        $shiftLeadPosititons = implode(',', [Position::OOD, Position::DEPUTY_OOD, Position::RSC_SHIFT_LEAD, Position::RSC_SHIFT_LEAD_PRE_EVENT]);

        $people = DB::table('person')
            ->select('person.id',
                'person.callsign',
                DB::raw("EXISTS (SELECT 1 FROM person_position WHERE person_position.person_id=person.id AND person_position.position_id IN ($shiftLeadPosititons) LIMIT 1) AS shift_lead"),
            )
            ->whereIn('person.status', [Person::ACTIVE, Person::INACTIVE, Person::INACTIVE_EXTENSION, Person::RETIRED])
            ->orderBy('callsign')
            ->get();

        foreach ($people as $person) {
            $person->signed_up = false;
            $person->hours_last_year = 0;
            $person->hours_prev_year = 0;
        }

        $ids = $people->pluck('id');
        $peopleById = $people->keyBy('id');

        $rows = DB::table('slot')
            ->select('person_id')
            ->join('person_slot', 'person_slot.slot_id', 'slot.id')
            ->whereYear('slot.begins', $currentYear)
            ->where('slot.begins', '>=', "$currentYear-08-15 00:00:00")
            ->whereNotIn('slot.position_id', [Position::ALPHA, Position::TRAINING])
            ->groupBy('person_id')
            ->get();

        foreach ($rows as $row) {
            if ($peopleById->has($row->person_id)) {
                $peopleById[$row->person_id]->signed_up = true;
            }
        }

        self::computeHours($peopleById, $ids, $lastYear, 'hours_last_year');
        self::computeHours($peopleById, $ids, $prevYear, 'hours_prev_year');

        // Person must have worked in one of the previous two years, or is a shift lead
        $people = $people->filter(fn($p) => ($p->hours_prev_year || $p->hours_last_year || $p->shift_lead))->values();

        foreach ($people as $person) {
            // Qualified radio hours is last year, OR the previous year if last year
            // was less than 10 hours and the previous year was greater than last year.
            $person->radio_hours = $person->hours_last_year;
            if ($person->hours_last_year < 10.0 && ($person->hours_prev_year > $person->hours_last_year)) {
                $person->radio_hours = $person->hours_prev_year;
            }
        }

        return $people->toArray();
    }

    public static function computeHours($peopleById, $ids, $year, $column): void
    {
        $rows = DB::table('timesheet')
            ->select(
                'person_id',
                DB::raw("SUM(timestampdiff(SECOND, on_duty, off_duty)) as total")
            )->whereIntegerInRaw('person_id', $ids)
            ->whereYear('on_duty', $year)
            ->whereNotIn('position_id', [Position::ALPHA, Position::TRAINING])
            ->groupBy('person_id')
            ->get();

        foreach ($rows as $row) {
            $peopleById[$row->person_id]->{$column} = round($row->total / 3600.0, 2);
        }
    }
}