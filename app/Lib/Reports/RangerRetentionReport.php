<?php

namespace App\Lib\Reports;

use App\Models\Person;
use App\Models\Position;
use App\Models\Timesheet;
use Illuminate\Support\Facades\DB;

class RangerRetentionReport
{
    public static function execute()
    {
        $year = current_year();
        $startYear = $year - 4;

        $people = DB::table('person')
            ->select('id', 'callsign', 'email', 'first_name', 'last_name', 'status')
            ->whereIn('status', [Person::ACTIVE, Person::INACTIVE, Person::INACTIVE_EXTENSION, Person::RETIRED])
            ->whereExists(function ($q) use ($startYear) {
                $q->select(DB::raw(1))
                    ->from('timesheet')
                    ->whereColumn('person.id', 'timesheet.person_id')
                    ->whereYear('on_duty', '>=', $startYear)
                    ->whereNotIn('position_id', [Position::ALPHA, Position::TRAINING, Position::DEEP_FREEZE])
                    ->where('is_non_ranger', false)
                    ->limit(1);
            })->orderBy('callsign')
            ->get();

        foreach ($people as $person) {
            $years = Timesheet::findYears($person->id, Timesheet::YEARS_RANGERED);
            $person->first_year = $years[0];
            $person->last_year = last($years);
            $person->total_years = count($years);
        }

        return [
            'start_year' => $startYear,
            'end_year' => $year,
            'people' => $people
        ];
    }
}