<?php

namespace App\Lib\Reports;

use App\Models\Person;
use App\Models\Position;
use Illuminate\Support\Facades\DB;

class PotentialEarnedShirtsReport
{
    /**
     * Retrieve folks who potentially earned a t-shirt
     *
     * @param int $year
     * @param int $thresholdSS
     * @param int $thresholdLS
     * @return array
     */

    public static function execute(int $year, int $thresholdSS, int $thresholdLS): array
    {
        $active_statuses = implode("','", Person::ACTIVE_STATUSES);
        $report = DB::select(
            "SELECT
          person.id, person.callsign, person.status, person.first_name, person.mi, person.last_name,
          eh.estimated_hours,
          ah.actual_hours,
          tshirt_swag.title AS t_shirt_size, 
          tshirt_secondary_swag.title as t_shirt_secondary_size,
          longsleeve_swag.title AS longsleeve_size
        FROM
          person
        LEFT JOIN swag tshirt_swag ON person.tshirt_swag_id = tshirt_swag.id
        LEFT JOIN swag tshirt_secondary_swag ON person.tshirt_secondary_swag_id = tshirt_secondary_swag.id
        LEFT JOIN swag longsleeve_swag ON person.long_sleeve_swag_id=longsleeve_swag.id
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


        return array_map(
            fn($row) => [
                'id' => $row->id,
                'callsign' => $row->callsign,
                'first_name' => $row->first_name,
                'middle_initial' => $row->mi,
                'last_name' => $row->last_name,
                'estimated_hours' => $row->estimated_hours,
                'actual_hours' => $row->actual_hours,
                'teeshirt_size_style' => $row->t_shirt_size ?? 'Unknown',
                'tshirt_secondary_size' => $row->t_shirt_secondary_size ?? 'Unknown',
                'longsleeveshirt_size_style' => $row->longsleeve_size ?? 'Unknown',

                'earned_ls' => ($row->actual_hours >= $thresholdLS),
                'earned_ss' => ($row->actual_hours >= $thresholdSS), // gonna be true always, but just in case the selection above changes.
            ], $report);
    }
}