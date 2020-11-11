<?php

namespace App\Lib\Reports;

use App\Models\Person;
use App\Models\Position;
use Illuminate\Support\Facades\DB;

class RadioEligibilityReport
{
    /*
     */

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

    public static function execute(int $currentYear)
    {
        // 2020 didn't happen, so adjust for that.
        if ($currentYear == 2021) {
            $lastYear = 2019;
            $prevYear = 2018;
        } else if ($currentYear == 2022) {
            $lastYear = 2021;
            $prevYear = 2019;
        } else {
            $lastYear = $currentYear - 1;
            $prevYear = $currentYear - 2;
        }

        $statuses = implode("','", [ Person::ACTIVE, Person::INACTIVE, Person::INACTIVE_EXTENSION, Person::RETIRED ]);
        $shiftLeadPosititons = implode(',', [Position::OOD, Position::DEPUTY_OOD, Position::RSC_SHIFT_LEAD, Position::RSC_SHIFT_LEAD_PRE_EVENT]);
        $excludePositions = implode(',', [ Position::ALPHA, Position::TRAINING]);

        $people = DB::select("SELECT person.id, person.callsign,
                (SELECT SUM(TIMESTAMPDIFF(second, on_duty, off_duty))/3600.0 FROM timesheet WHERE person.id=timesheet.person_id AND year(on_duty)=$lastYear AND position_id NOT IN (1,13)) as hours_last_year,
                (SELECT SUM(TIMESTAMPDIFF(second, on_duty, off_duty))/3600.0 FROM timesheet WHERE person.id=timesheet.person_id AND year(on_duty)=$prevYear AND position_id NOT IN (1,13)) as hours_prev_year,
                EXISTS (SELECT 1 FROM person_position WHERE person_position.person_id=person.id AND person_position.position_id IN ($shiftLeadPosititons) LIMIT 1) AS shift_lead,
                EXISTS (SELECT 1 FROM person_slot JOIN slot ON person_slot.slot_id=slot.id AND YEAR(slot.begins)=$currentYear AND slot.begins >= '$currentYear-08-15 00:00:00' AND position_id NOT IN ($excludePositions) WHERE person_slot.person_id=person.id LIMIT 1) as signed_up
                FROM person WHERE person.status IN ('$statuses')
                ORDER by callsign");

        // Person must have worked in one of the previous two years, or is a shift lead
        $people = array_values(array_filter($people, function ($p) {
            return $p->hours_prev_year || $p->hours_last_year || $p->shift_lead;
        }));

        foreach ($people as $person) {
            // Normalized the hours - no timesheets found in a given years will result in null
            if (!$person->hours_last_year) {
                $person->hours_last_year = 0.0;
            }

            $person->hours_last_year = round($person->hours_last_year, 2);

            if (!$person->hours_prev_year) {
                $person->hours_prev_year = 0.0;
            }
            $person->hours_prev_year = round($person->hours_prev_year, 2);

            // Qualified radio hours is last year, OR the previous year if last year
            // was less than 10 hours and the previous year was greater than last year.
            $person->radio_hours = $person->hours_last_year;
            if ($person->hours_last_year < 10.0 && ($person->hours_prev_year > $person->hours_last_year)) {
                $person->radio_hours = $person->hours_prev_year;
            }
        }

        return $people;
    }
}