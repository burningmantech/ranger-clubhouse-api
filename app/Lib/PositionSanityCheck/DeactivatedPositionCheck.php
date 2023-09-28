<?php

namespace App\Lib\PositionSanityCheck;

use App\Models\Person;
use App\Models\PersonPosition;
use App\Models\Position;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DeactivatedPositionCheck
{
    public static function issues(): array
    {
        $rows = DB::table('person')
            ->select(
                'person.id AS person_id',
                'person.callsign',
                'person.status',
                'position.id AS position_id',
                'position.title AS position_title'
            )->join('person_position', 'person.id', 'person_position.person_id')
            ->join('position', 'position.id', 'person_position.position_id')
            ->where('position.active', false)
            ->whereNotIn('person.status', Person::DEACTIVATED_STATUSES)
            ->orderBy('position_title')
            ->orderBy('person.callsign')
            ->get();

        $positions = [];
        $people = [];
        foreach ($rows as $row) {
            $position = self::position($row);

            if (!in_array($position, $positions)) {
                $positions[] = $position;
            }

            $people[$row->position_id][] = [
                "id" => $row->person_id,
                "callsign" => $row->callsign,
                "status" => $row->status
            ];
        }

        foreach ($positions as $i => $position) {
            $positions[$i]['people'] = $people[$position['id']];
        }

        return $positions;
    }

    public static function repair($peopleIds, ...$options): array
    {
        $results = [];
        $positionId = $options[0]['positionId'];

        // Validate position exists and is inactive
        $position = Position::where('id', $positionId)->where('active', 0)->get();
        if ($position->count() == 0) {
            throw new InvalidArgumentException("Invalid position!");
        }

        foreach ($peopleIds as $personId) {
            PersonPosition::removeIdsFromPerson($personId, [$positionId], 'position sanity check repair');
            $results[] = [
                'id' => $personId,
                'messages' => ['position removed']
            ];
        }

        return $results;
    }

    private static function position($obj): array
    {
        return [
            'id' => $obj->position_id,
            'title' => $obj->position_title
        ];
    }
}
