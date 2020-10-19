<?php

namespace App\Lib\PositionSanityCheck;

use Illuminate\Support\Facades\DB;

class DeactivatedPositionCheck
{
    public static function issues(): array
    {
        $rows = DB::select(
           "SELECT
              person.id AS person_id, person.callsign, person.status,
              position.id AS position_id, position.title AS position_title
            FROM
              person
            JOIN
              person_position ON person_position.person_id = person.id
            JOIN
              position ON position.id = person_position.position_id
            WHERE
              position.active IS FALSE
            ORDER BY position_title, person.callsign"
        );

        $positions = [];
        $people = [];
        foreach($rows as $row) {
            $position = self::position($row);

            if (!in_array($position, $positions)) {
                $positions[] = $position;
            }

            $people[$row->position_id][] = [
                "id"       => $row->person_id,
                "callsign" => $row->callsign,
                "status"   => $row->status
            ];
        }

        foreach($positions as $i=>$position) {
            $positions[$i]['people'] = $people[$position['id']];
        }

        return $positions;
    }

    public static function repair($peopleIds): array
    {
    }

    private static function position($obj): array
    {
        return [
            'id' => $obj->position_id,
            'title' => $obj->position_title
            ];
    }
}
