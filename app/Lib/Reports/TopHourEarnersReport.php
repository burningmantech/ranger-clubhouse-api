<?php

namespace App\Lib\Reports;

use App\Models\Person;
use App\Models\Timesheet;
use Illuminate\Support\Collection;

class TopHourEarnersReport
{
    /**
     * Retrieve the top N people worked within a given year range.
     *
     * @param int $startYear
     * @param int $endYear
     * @param int $topLimit the top N people
     * @return Collection
     */

    public static function execute(int $startYear, int $endYear, int $topLimit)
    {
        // Find all eligible candidates
        $people = Person::select('id', 'callsign', 'status', 'email')
            ->whereIn('status', [Person::ACTIVE, Person::INACTIVE])
            ->get();

        $candidates = collect([]);

        foreach ($people as $person) {
            for ($year = $endYear; $year >= $startYear; $year = $year - 1) {
                // Walk backward thru time and find the most recent year worked.
                $seconds = Timesheet::join('position', 'timesheet.position_id', 'position.id')
                    ->where('person_id', $person->id)
                    ->whereYear('on_duty', $year)
                    ->where('position.count_hours', true)
                    ->get()
                    ->sum('duration');
                if ($seconds > 0) {
                    // Hey found a candidate
                    $candidates[] = (object)[
                        'person' => $person,
                        'seconds' => $seconds,
                        'year' => $year
                    ];
                    break;
                }
            }
        }

        $candidates = $candidates->sortByDesc('seconds')->splice(0, $topLimit);

        return $candidates->map(function ($c) {
            $person = $c->person;
            return [
                'id' => $person->id,
                'callsign' => $person->callsign,
                'status' => $person->status,
                'email' => $person->email,
                'hours' => round($c->seconds / 3600.0, 2),
                'year' => $c->year,
            ];
        });
    }
}