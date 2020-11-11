<?php

namespace App\Lib\Reports;

use App\Models\Person;
use App\Models\Position;
use Illuminate\Support\Facades\DB;

class PotentialEarnedShirtsReport
{
    /*
      * Retrieve folks who potentially earned a t-shirt
      */

    public static function execute($year, $thresholdSS, $thresholdLS)
    {
        $active_statuses = implode("','", Person::ACTIVE_STATUSES);
        $report = DB::select(
            "SELECT
          person.id, person.callsign, person.status, person.first_name, person.mi, person.last_name,
          eh.estimated_hours,
          ah.actual_hours,
          person.teeshirt_size_style, person.longsleeveshirt_size_style
        FROM
          person
        LEFT JOIN (
          SELECT
            person_slot.person_id,
            round(sum(((TIMESTAMPDIFF(MINUTE, slot.begins, slot.ends))/60)),2) AS estimated_hours
          FROM
            slot
          JOIN
            person_slot ON person_slot.slot_id = slot.id
          JOIN
            position ON position.id = slot.position_id
          WHERE
            YEAR(slot.begins) = ?
            AND position.count_hours IS TRUE
          GROUP BY person_id
        ) eh ON eh.person_id = person.id

        LEFT JOIN (
          SELECT
            timesheet.person_id,
            round(sum(((TIMESTAMPDIFF(MINUTE, timesheet.on_duty, timesheet.off_duty))/60)),2) AS actual_hours
          FROM
            timesheet
          JOIN
            position ON position.id = timesheet.position_id
          WHERE
            YEAR(timesheet.on_duty) = ?
            AND position.count_hours IS TRUE
          GROUP BY person_id
        ) ah ON ah.person_id = person.id

        WHERE
          ( actual_hours > 0 OR estimated_hours > 0 )
          AND person.id NOT IN (
            SELECT
              timesheet.person_id
            FROM
              timesheet
            JOIN
              position ON position.id = timesheet.position_id
            WHERE
              YEAR(timesheet.on_duty) = ?
              AND position_id = ?
          )
          AND person.status IN ('" . $active_statuses . "')
        ORDER BY
          person.callsign
        "
            , [$year, $year, $year, Position::ALPHA]
        );

        if (empty($report)) {
            return [];
        }

        $report = collect($report);
        return $report->map(function ($row) use ($thresholdSS, $thresholdLS) {
            return [
                'id' => $row->id,
                'callsign' => $row->callsign,
                'first_name' => $row->first_name,
                'middle_initial' => $row->mi,
                'last_name' => $row->last_name,
                'estimated_hours' => $row->estimated_hours,
                'actual_hours' => $row->actual_hours,
                'longsleeveshirt_size_style' => $row->longsleeveshirt_size_style,
                'earned_ls' => ($row->actual_hours >= $thresholdLS),
                'teeshirt_size_style' => $row->teeshirt_size_style,
                'earned_ss' => ($row->actual_hours >= $thresholdSS), // gonna be true always, but just in case the selection above changes.
            ];
        });
    }
}