<?php

namespace App\Lib\Reports;

use App\Models\AccessDocument;
use App\Models\Person;
use App\Models\Timesheet;
use Illuminate\Support\Facades\DB;

class TopHourEarnersReport
{
    /**
     * Retrieve the top N people worked within a given year range.
     *
     * @param int $startYear
     * @param int $endYear
     * @param int $topLimit the top N people
     * @return array
     */

    public static function execute(int $startYear, int $endYear, int $topLimit): array
    {
        // Find all eligible candidates
        $people = DB::table('person')
            ->select('id', 'callsign', 'first_name', 'last_name', 'status', 'email')
            ->whereIn('status', [Person::ACTIVE, Person::INACTIVE])
            ->get();

        $personIds = $people->pluck('id');

        $bigBank = DB::table('access_document')
            ->whereIntegerInRaw('person_id', $personIds)
            ->whereIn('type', AccessDocument::TICKET_TYPES)
            ->whereIn('status', [AccessDocument::BANKED, AccessDocument::QUALIFIED])
            ->get()
            ->groupBy('person_id');

        $candidates = collect([]);

        $timesheetsByPersonId = Timesheet::select('timesheet.*')
            ->join('position', 'timesheet.position_id', 'position.id')
            ->whereIntegerInRaw('person_id', $personIds)
            ->whereYear('on_duty', '>=', $startYear)
            ->whereYear('on_duty', '<=', $endYear)
            ->where('position.count_hours', true)
            ->get()
            ->groupBy('person_id');

        foreach ($people as $person) {
            $totalDuration = 0;
            $topDuration = 0;
            $topYear = 0;
            $entries = $timesheetsByPersonId->get($person->id);
            $years = [];

            if ($entries) {
                $entries = collect($entries)->groupBy(fn($e) => $e->on_duty->year);

                for ($year = $startYear; $year <= $endYear; $year++) {
                    $worked = $entries->get($year);
                    $duration = $worked ? $worked->sum('duration') : 0;

                    if ($topDuration < $duration) {
                        $topDuration = $duration;
                        $topYear = $year;
                    }

                    $totalDuration += $duration;
                    $years[] = [
                        'year' => $year,
                        'duration' => $duration,
                    ];
                }
            }

            if ($totalDuration > 0) {
                $ticketsBanked = $bigBank->get($person->id);
                if ($ticketsBanked) {
                    $banked = [];
                    foreach ($ticketsBanked as $ticket) {
                        $banked[] = AccessDocument::SHORT_TICKET_LABELS[$ticket->type] ?? $ticket->type;
                    }
                    $banked = implode('+', $banked);
                } else {
                    $banked = '';
                }
                $candidates[] = (object)[
                    'person' => $person,
                    'years' => $years,
                    'top_year' => $topYear,
                    'top_duration' => $topDuration,
                    'total_duration' => $totalDuration,
                    'banked' => $banked
                ];
            }
        }

        $candidates = $candidates->sortByDesc('top_duration')->slice(0, $topLimit)->values();

        return $candidates->map(function ($c) {
            $person = $c->person;
            return [
                'id' => $person->id,
                'callsign' => $person->callsign,
                'first_name' => $person->first_name,
                'last_name'=> $person->last_name,
                'status' => $person->status,
                'email' => $person->email,
                'top_duration' => $c->top_duration,
                'top_year' => $c->top_year,
                'total_duration' => $c->total_duration,
                'years' => $c->years,
            ];
        })->toArray();
    }
}