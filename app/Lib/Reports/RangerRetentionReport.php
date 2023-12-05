<?php

namespace App\Lib\Reports;

use App\Models\Person;
use App\Models\Position;
use App\Models\Timesheet;
use Illuminate\Support\Facades\DB;

class RangerRetentionReport
{
    public static function execute(): array
    {
        $year = current_year();

        // Handle pandemic year gaps
        $startYear = match ($year) {
            2023 => 2017,
            2024 => 2018,
            2025 => 2019,
            default => $year - 4,
        };

        $rows = Person::select('id', 'callsign', 'email', 'first_name', 'preferred_name', 'last_name', 'status')
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

        $people = [];
        foreach ($rows as $person) {
            $years = Timesheet::findYears($person->id, Timesheet::YEARS_RANGERED);
            $people[] = [
                'id' => $person->id,
                'callsign' => $person->callsign,
                'email' => $person->email,
                'first_name' => $person->desired_first_name(),
                'last_name' => $person->last_name,
                'status' => $person->status,
                'first_year' => $years[0],
                'last_year' => last($years),
                'total_years' => count($years),
            ];
        }

        return [
            'start_year' => $startYear,
            'end_year' => $year,
            'people' => $people
        ];
    }
}