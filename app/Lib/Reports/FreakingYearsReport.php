<?php


namespace App\Lib\Reports;


use App\Models\Person;
use App\Models\Position;
use Illuminate\Support\Facades\DB;

class FreakingYearsReport
{
    /**
     * Build a Freaking Years report - how long a person has rangered, the first year rangered, the last year to ranger,
     * and if the person intends to ranger in the intended year (usually the current year)
     *
     * Non Ranger work entries (i.e. a person who worked as Non Ranger status volunteer) are excluded.
     *
     * @param bool $showAll false if only report on active status rangers, otherwise everyone.
     * @param int $intendToWorkYear year the person might work in (usually the current year)
     * @return array|\Illuminate\Support\Collection
     */

    public static function execute(bool $showAll, int $intendToWorkYear)
    {
        $excludePositionIds = implode(',', [Position::ALPHA, Position::HQ_RUNNER]);
        $statusCond = $showAll ? '' : 'person.status="active" AND ';

        $rows = DB::select(
            'SELECT E.person_id, sum(year) AS years, ' .
            "(SELECT YEAR(on_duty) FROM timesheet ts WHERE ts.person_id=E.person_id AND YEAR(ts.on_duty) > 0 GROUP BY YEAR(ts.on_duty) ORDER BY YEAR(ts.on_duty) ASC LIMIT 1) AS first_year, " .
            "(SELECT YEAR(on_duty) FROM timesheet ts WHERE ts.person_id=E.person_id AND YEAR(ts.on_duty) > 0 GROUP BY YEAR(ts.on_duty) ORDER BY YEAR(ts.on_duty) DESC LIMIT 1) AS last_year, " .
            "EXISTS (SELECT 1 FROM person_slot JOIN slot ON slot.id=person_slot.slot_id AND YEAR(slot.begins)=$intendToWorkYear WHERE person_slot.person_id=E.person_id LIMIT 1) AS signed_up " .
            'FROM (SELECT person.id as person_id, COUNT(DISTINCT(YEAR(on_duty))) AS year FROM ' .
            "person, timesheet WHERE $statusCond person.id = person_id " .
            "AND position_id NOT IN ($excludePositionIds) " .
            "AND is_non_ranger is false " .
            'GROUP BY person.id, YEAR(on_duty)) AS E ' .
            'GROUP BY E.person_id'
        );
        if (empty($rows)) {
            return [];
        }

        $personIds = array_column($rows, 'person_id');
        $people = Person::select('id', 'callsign', 'first_name', 'last_name', 'status')
            ->whereIntegerInRaw('id', $personIds)
            ->get()
            ->keyBy('id');

        $freaks = array_map(function ($row) use ($people) {
            $person = $people[$row->person_id];
            return [
                'id' => $row->person_id,
                'callsign' => $person->callsign,
                'status' => $person->status,
                'first_name' => $person->first_name,
                'last_name' => $person->last_name,
                'years' => (int)$row->years,
                'first_year' => (int)$row->first_year,
                'last_year' => (int)$row->last_year,
                'signed_up' => (int)$row->signed_up,
            ];
        }, $rows);

        usort($freaks, function ($a, $b) {
            if ($a['years'] == $b['years']) {
                return strcasecmp($a['callsign'], $b['callsign']);
            } else {
                return $b['years'] - $a['years'];
            }
        });

        return collect($freaks)->groupBy('years')->map(function ($people, $year) {
            return ['years' => $year, 'people' => $people];
        })->values();
    }
}