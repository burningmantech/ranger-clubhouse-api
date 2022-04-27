<?php

namespace App\Lib\Reports;

use App\Models\Person;
use Illuminate\Support\Facades\DB;

class SpecialTeamsWorkReport
{
    /**
     * Retrieve time worked on special teams
     *
     * @param array $positionIds positions to report on
     * @param int $startYear beginning year
     * @param int $endYear ending year
     * @param bool $includeInactive include inactive Rangers
     * @param bool $viewEmail include email addresses
     * @return array
     */
    public static function execute(array $positionIds, int $startYear, int $endYear, bool $includeInactive, bool $viewEmail = false)
    {
        $sql = DB::table('person')
            ->select(
                'person.id as person_id',
                DB::raw('IFNULL(person_position.position_id, timesheet.position_id) as position_id'),
                DB::raw('YEAR(timesheet.on_duty) as year'),
                DB::raw('SUM( TIMESTAMPDIFF( SECOND, timesheet.on_duty, timesheet.off_duty ) ) AS duration')
            )
            ->leftJoin('person_position', function ($q) use ($positionIds) {
                $q->on('person_position.person_id', 'person.id');
                $q->whereIn('person_position.position_id', $positionIds);
            })
            ->leftJoin('timesheet', function ($q) use ($startYear, $endYear, $positionIds) {
                $q->on('timesheet.person_id', 'person.id');
                $q->whereYear('on_duty', '>=', $startYear);
                $q->whereYear('on_duty', '<=', $endYear);
                $q->whereIn('timesheet.position_id', $positionIds);
            })
            ->where(function ($q) {
                $q->whereNotNull('timesheet.id');
                $q->orWhereNotNull('person_position.position_id');
            })
            ->groupBy('person.id')
            ->groupBy(DB::raw('IFNULL(person_position.position_id, timesheet.position_id)'))
            ->groupBy('year')
            ->orderBy('person.id')
            ->orderBy('year');

        if (!$includeInactive) {
            $sql->whereNotNull('timesheet.id');
        }

        $rows = $sql->get();
        $peopleByIds = Person::whereIntegerInRaw('id', $rows->pluck('person_id')->unique())->get()->keyBy('id');
        $rows = $rows->groupBy('person_id');

        $results = [];

        foreach ($rows as $personId => $worked) {
            $timeByYear = $worked->keyBy('year');

            $person = $peopleByIds[$personId];

            $years = [];
            $totalDuration = 0;
            for ($year = $startYear; $year <= $endYear; $year++) {
                $duration = (int)($timeByYear->has($year) ? $timeByYear[$year]->duration : 0);
                $years[] = $duration;
                $totalDuration += $duration;
            }

            $result = [
                'id' => $person->id,
                'callsign' => $person->callsign,
                'first_name' => $person->first_name,
                'last_name' => $person->last_name,
                'status' => $person->status,
                'years' => $years,
                'total_duration' => $totalDuration
            ];

            if ($viewEmail) {
                $result['email'] = $person->email;
            }

            $results[] = $result;
        }

        usort($results, function ($a, $b) {
            return strcasecmp($a['callsign'], $b['callsign']);
        });

        return $results;
    }
}