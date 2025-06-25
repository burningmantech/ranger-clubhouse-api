<?php

namespace App\Lib;

use App\Models\Person;
use App\Models\PersonAward;
use App\Models\Timesheet;
use Illuminate\Support\Facades\DB;

class YearsManagement
{
    public static function updateSignupYears(int $personId): void
    {
        $person = Person::find($personId);
        $person->years_of_signups = DB::table('person_slot')
            ->selectRaw("YEAR(begins) as year")
            ->join('slot', 'slot.id', '=', 'person_slot.slot_id')
            ->where('person_id', $personId)
            ->groupBy('year')
            ->orderBy('year')
            ->pluck('year')
            ->toArray();

        self::savePerson($person, 'signup years recompute');
    }

    public static function updateTimesheetYears(int $personId): void
    {
        $person = Person::find($personId);
        $person->years_as_contributor = Timesheet::computeYearsForPerson($personId, Timesheet::YEARS_AS_CONTRIBUTOR);
        $person->years_as_ranger = Timesheet::computeYearsForPerson($personId, Timesheet::YEARS_AS_RANGER);
        $person->years_combined = Timesheet::computeYearsForPerson($personId, Timesheet::YEARS_ALL);
        self::savePerson($person, 'timesheet years recompute');
    }

    /**
     * Update the years of service for a person.
     *
     * @param int $personId
     * @return void
     */

    public static function updateYearsOfAwards(int $personId): void
    {
        $person = Person::find($personId);
        $person->years_of_awards = DB::table('person_award')
            ->where('person_id', $personId)
            ->distinct()
            ->where('awards_grants_service_year', true)
            ->orderBy('year')
            ->get(['year'])
            ->pluck('year')
            ->toArray();

        self::savePerson($person, 'award years recompute');
    }

    public static function savePerson(Person $person, string $reason): void
    {
        $seen = array_merge($person->years_combined, $person->years_of_signups);
        $seen = array_unique($seen);
        sort($seen, SORT_NUMERIC);
        $person->years_seen = $seen;

        $serviceYears = array_merge($person->years_as_ranger, $person->years_of_awards, $person->years_as_contributor);
        $serviceYears = array_unique($serviceYears);
        sort($serviceYears);
        $person->years_of_service = $serviceYears;

        $person->auditReason = $reason;
        $person->saveWithoutValidation();
    }
}