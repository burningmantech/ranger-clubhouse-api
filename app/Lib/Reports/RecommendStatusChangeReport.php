<?php

namespace App\Lib\Reports;

use App\Models\Person;
use App\Models\Position;
use Illuminate\Support\Facades\DB;

class RecommendStatusChangeReport
{

    /**
     * Report on people who should have their status updated.
     *
     * @param int $year
     * @return array
     */

    public static function execute(int $year): array
    {

        // Sigh, skip over the lost pandemic years 2020 & 2021.
        switch ($year) {
            case 2022:
                $inactiveYear = 2018;
                $retiredYear = 2016;
                break;

            case 2023:
                $inactiveYear = 2019;
                $retiredYear = 2017;
                break;

            case 2024:
                $inactiveYear = 2022;
                $retiredYear = 2018;
                break;

            case 2025:
                $inactiveYear = 2023;
                $retiredYear = 2019;
                break;

            default:
                $inactiveYear = $year - 2;
                $retiredYear = $year - 4;
                break;
        }


        // Filter out testing accounts, and temporary laminates.
        $filterTestAccounts = fn($r) => !preg_match('/(^(ROC Monitor|testing|lam #|temp \d+))|\(test\)/i', $r->callsign);

        $excludePositionsWithAlpha = implode(',', [Position::ALPHA, Position::TRAINING, Position::DEEP_FREEZE]);
        $excludePositions = implode(',', [Position::TRAINING, Position::DEEP_FREEZE]);

        $yearsRangered = DB::raw("(SELECT COUNT(DISTINCT(YEAR(on_duty))) FROM timesheet WHERE person_id=person.id AND position_id NOT IN ($excludePositionsWithAlpha)) AS years");
        $lastYear = DB::raw('(SELECT YEAR(on_duty) FROM timesheet WHERE person_id=person.id ORDER BY on_duty DESC LIMIT 1) AS last_year');
        $alphaYear = DB::raw('(SELECT YEAR(on_duty) FROM timesheet WHERE position_id=1 AND person_id=person.id ORDER BY on_duty DESC LIMIT 1) AS alpha_year');

        // Inactive means that you have not rangered in any of the last 3 events
        // but you have rangered in at least one of the last 5 events
        $inactives = Person::select(
            'id',
            'callsign',
            'status',
            'email',
            'vintage',
            $lastYear,
            $yearsRangered,
            $alphaYear,
        )->where('status', Person::ACTIVE)
            ->whereRaw('person.id NOT IN (SELECT person_id FROM timesheet WHERE YEAR(on_duty) >= ?)', [$inactiveYear])
            ->whereRaw("person.id IN (SELECT person_id FROM timesheet WHERE position_id NOT IN ($excludePositions) AND YEAR(on_duty) >= ?)", [$retiredYear])
            ->orderBy('callsign')
            ->get()
            ->filter($filterTestAccounts)
            ->values();

        self::patchUpAlphaYear($inactives);

        // Retired means that you have not rangered in any of the last 5 events
        $retired = Person::select(
            'id',
            'callsign',
            'status',
            'email',
            'vintage',
            $lastYear,
            $yearsRangered,
            $alphaYear,
        )->whereIn('status', [Person::ACTIVE, Person::INACTIVE, Person::INACTIVE_EXTENSION])
            ->whereRaw("person.id NOT IN (SELECT person_id FROM timesheet WHERE position_id NOT IN ($excludePositions) AND YEAR(on_duty) >= ?)", [$retiredYear])
            ->orderBy('callsign')
            ->get()
            ->filter($filterTestAccounts)
            ->values();

        self::patchUpAlphaYear($retired);

        // Mark as vintage are people who have been active for 10 years or more.
        $vintage = DB::table('timesheet')
            ->select(
                'person_id as id',
                'callsign',
                'status',
                'email',
                'vintage',
                DB::raw('YEAR(MAX(on_duty)) AS last_year'),
                DB::raw('count(distinct(YEAR(on_duty))) as years'),
                DB::raw('(SELECT YEAR(at.on_duty) FROM timesheet at WHERE at.position_id=1 AND at.person_id=timesheet.person_id ORDER BY at.on_duty DESC LIMIT 1) AS alpha_year'),
            )
            ->join('person', 'person.id', 'timesheet.person_id')
            ->whereIn('status', [Person::ACTIVE, Person::INACTIVE])
            ->whereNotIn('position_id', [Position::ALPHA, Position::TRAINING])
            ->where('vintage', false)
            ->groupBy(['person_id', 'callsign', 'status', 'email', 'vintage'])
            ->havingRaw('count(distinct(YEAR(on_duty))) >= 10')
            ->orderBy('callsign')
            ->get()
            ->filter($filterTestAccounts)
            ->values();

        self::patchUpAlphaYear($vintage);

        // People who have been active in the last three events yet are listed as inactive
        $actives = Person::select(
            'id',
            'callsign',
            'status',
            'email',
            'vintage',
            $lastYear,
            $yearsRangered,
            $alphaYear,
        )->whereIn('status', [Person::INACTIVE, Person::INACTIVE_EXTENSION, Person::RETIRED])
            ->whereRaw("person.id IN (SELECT person_id FROM timesheet WHERE position_id NOT IN ($excludePositions) AND YEAR(on_duty) >= ?)", [$inactiveYear])
            ->orderBy('callsign')
            ->get()
            ->filter($filterTestAccounts)
            ->values();

        self::patchUpAlphaYear($actives);

        // Any PNVs not passed will need to be converted to past prospective status
        $pastProspectives = Person::select('id', 'callsign', 'status', 'email')
            ->whereIn('status', [Person::BONKED, Person::ALPHA, Person::PROSPECTIVE])
            ->orderBy('callsign')
            ->get()
            ->filter($filterTestAccounts)->values();

        return [
            'inactive_year' => $inactiveYear,
            'retired_year' => $retiredYear,
            'inactives' => $inactives,
            'retired' => $retired,
            'actives' => $actives,
            'past_prospectives' => $pastProspectives,
            'vintage' => $vintage
        ];
    }

    /**
     * Timesheets prior to 2008 do not have an alpha entry, only a single placeholder dirt entry for each year worked.
     * Find the most distant dirt entry to determine the alpha year.
     *
     * @param $rows
     * @return void
     */

    public static function patchUpAlphaYear($rows): void
    {
        foreach ($rows as $row) {
            if ($row->alpha_year) {
                continue;
            }

            $row->alpha_year = DB::table('timesheet')
                ->selectRaw('YEAR(on_duty) as year')
                ->where('position_id', Position::DIRT)
                ->where('person_id', $row->id)
                ->orderBy('on_duty')
                ->first()?->year;
        }
    }
}