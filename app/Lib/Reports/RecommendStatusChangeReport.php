<?php

namespace App\Lib\Reports;

use App\Models\Person;
use App\Models\Position;
use Illuminate\Support\Facades\DB;

class RecommendStatusChangeReport {

    /**
     * Report on people who should have their status updated.
     *
     * @param int $year
     * @return array
     */

    public static function execute(int $year) : array
    {
        $filterTestAccounts = function ($r) {
            // Filter out testing accounts, and temporary laminates.
            return !preg_match('/(^(testing|lam #|temp \d+))|\(test\)/i', $r->callsign);
        };

        $yearsRangered = DB::raw('(SELECT COUNT(DISTINCT(YEAR(on_duty))) FROM timesheet WHERE person_id=person.id AND position_id NOT IN (1, 13, 29, 30)) AS years');
        $lastYear = DB::raw('(SELECT YEAR(on_duty) FROM timesheet WHERE person_id=person.id ORDER BY on_duty DESC LIMIT 1) AS last_year');

        // Inactive means that you have not rangered in any of the last 3 events
        // but you have rangered in at least one of the last 5 events
        $inactives = Person::select(
            'id',
            'callsign',
            'status',
            'email',
            'vintage',
            $lastYear,
            $yearsRangered
        )->where('status', 'active')
            ->whereRaw('person.id NOT IN (SELECT person_id FROM timesheet WHERE YEAR(on_duty) BETWEEN ? AND ?)', [$year - 3, $year])
            ->whereRaw('person.id IN (SELECT person_id FROM timesheet WHERE YEAR(on_duty) BETWEEN ? AND ?)', [$year - 5, $year - 4])
            ->orderBy('callsign')
            ->get()
            ->filter($filterTestAccounts)->values();

        // Retired means that you have not rangered in any of the last 5 events
        $retired = Person::select(
            'id',
            'callsign',
            'status',
            'email',
            'vintage',
            $lastYear,
            $yearsRangered
        )->whereIn('status', [Person::ACTIVE, Person::INACTIVE, Person::INACTIVE_EXTENSION])
            ->whereRaw('person.id NOT IN (SELECT person_id FROM timesheet WHERE YEAR(on_duty) BETWEEN ? AND ?)', [$year - 5, $year])
            ->orderBy('callsign')
            ->get()
            ->filter($filterTestAccounts)->values();


        // Mark as vintage are people who have been active for 10 years or more.
        $vintage = DB::table('timesheet')
            ->select(
                'person_id as id',
                'callsign',
                'status',
                'email',
                'vintage',
                DB::raw('YEAR(MAX(on_duty)) AS last_year'),
                DB::raw('count(distinct(YEAR(on_duty))) as years')
            )
            ->join('person', 'person.id', 'timesheet.person_id')
            ->whereIn('status', [Person::ACTIVE, Person::INACTIVE])
            ->whereNotIn('position_id', [Position::ALPHA, Position::TRAINING])
            ->where('vintage', false)
            ->groupBy(['person_id', 'callsign', 'status', 'email', 'vintage'])
            ->havingRaw('count(distinct(YEAR(on_duty))) >= 10')
            ->orderBy('callsign')
            ->get()
            ->filter($filterTestAccounts)->values();

        // People who have been active in the last three events yet are listed as inactive
        $actives = Person::select(
            'id',
            'callsign',
            'status',
            'email',
            'vintage',
            $lastYear,
            $yearsRangered
        )->whereIn('status', [Person::INACTIVE, Person::INACTIVE_EXTENSION, Person::RETIRED])
            ->whereRaw('person.id IN (SELECT person_id FROM timesheet WHERE YEAR(on_duty) BETWEEN ? AND ?)', [$year - 3, $year])
            ->orderBy('callsign')
            ->get()
            ->filter($filterTestAccounts)->values();

        $pastProspectives = Person::select('id', 'callsign', 'status', 'email')
            ->whereIn('status', [Person::BONKED, Person::ALPHA, Person::PROSPECTIVE])
            ->orderBy('callsign')
            ->get()
            ->filter($filterTestAccounts)->values();

        return [
            'inactives' => $inactives,
            'retired' => $retired,
            'actives' => $actives,
            'past_prospectives' => $pastProspectives,
            'vintage' => $vintage
        ];
    }
}