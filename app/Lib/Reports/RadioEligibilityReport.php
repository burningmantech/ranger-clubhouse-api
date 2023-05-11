<?php

namespace App\Lib\Reports;

use App\Models\Person;
use App\Models\Position;
use Illuminate\Support\Facades\DB;

class RadioEligibilityReport
{
    const SHIFT_LEAD_POSITIONS = [
        Position::OOD,
        Position::DEPUTY_OOD,
        Position::RSC_SHIFT_LEAD,
        Position::RSC_SHIFT_LEAD_PRE_EVENT
    ];

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
        switch ($currentYear) {
            case 2022:
                $year1 = 2019;
                $year2 = 2018;
                $year3 = 2017;
                break;
            case 2023:
                $year1 = 2022;
                $year2 = 2019;
                $year3 = 2018;
                break;
            case 2024:
                $year1 = 2023;
                $year2 = 2022;
                $year3 = 2019;
                break;
            default:
                $year1 = $currentYear - 1;
                $year2 = $currentYear - 2;
                $year3 = $currentYear - 3;
                break;
        }

        $shiftLeadPosititons = implode(',', self::SHIFT_LEAD_POSITIONS);

        $people = DB::table('person')
            ->select('.id', '.callsign')
            ->whereIn('status', Person::ACTIVE_STATUSES)
            ->orderBy('callsign')
            ->get();

        foreach ($people as $person) {
            $person->signed_up = false;
            $person->shift_lead = false;
            $person->year_1 = 0;
            $person->year_2 = 0;
            $person->year_3 = 0;
        }

        $ids = $people->pluck('id');
        $peopleById = $people->keyBy('id');

        $rows = DB::table('slot')
            ->select('person_id')
            ->join('person_slot', 'person_slot.slot_id', 'slot.id')
            ->where('slot.begins_year', $currentYear)
            ->where('slot.begins', '>=', "$currentYear-08-15 00:00:00")
            ->whereNotIn('slot.position_id', [Position::ALPHA, Position::TRAINING])
            ->groupBy('person_id')
            ->get();

        foreach ($rows as $row) {
            if ($peopleById->has($row->person_id)) {
                $peopleById[$row->person_id]->signed_up = true;
            }
        }

        $rows = DB::table('person_position')
            ->select('person_id')
            ->whereIn('person_id', $ids)
            ->whereIn('position_id', self::SHIFT_LEAD_POSITIONS)
            ->distinct()
            ->get();

        foreach ($rows as $row) {
            $peopleById[$row->person_id]->shift_lead = true;
        }

        self::computeHours($peopleById, $ids, $year1, 'year_1');
        self::computeHours($peopleById, $ids, $year2, 'year_2');
        self::computeHours($peopleById, $ids, $year3, 'year_3');

        // Person must have worked in one of the previous three years, or is a shift lead
        $people = $people->filter(fn($p) => ($p->year_1 || $p->year_2 || $p->year_3 || $p->shift_lead))->values();

        foreach ($people as $person) {
            // Qualified radio hours is last year, OR the previous year if last year
            // was less than 10 hours and the previous year was greater than last year.
            $person->radio_hours = $person->year_1;
            if ($person->year_1 < 10.0 && ($person->year_2 > $person->year_1)) {
                $person->radio_hours = $person->year_2;
            }
            if ($person->year_3 > $person->radio_hours) {
                $person->radio_hours = $person->year_3;
            }
        }

        return [
            'people' => $people->toArray(),
            'year_1' => $year1,
            'year_2' => $year2,
            'year_3' => $year3,
            'current_year' => $currentYear,
            'shift_lead_positions' => DB::table('position')
                ->select('id', 'title')
                ->whereIn('id', self::SHIFT_LEAD_POSITIONS)
                ->orderBy('title')
                ->get(),
        ];
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