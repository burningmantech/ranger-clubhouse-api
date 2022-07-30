<?php

namespace App\Lib\PositionSanityCheck;

use App\Models\Position;
use App\Models\PersonPosition;

use Illuminate\Support\Facades\DB;

class GreenDotCheck extends SanityCheck
{
    public static function issues(): array
    {
        return  DB::select(
            "SELECT * FROM (SELECT p.id AS id, callsign, status, " .
            "EXISTS (SELECT 1 FROM person_position WHERE person_id = p.id AND position_id = ?) AS has_dirt_green_dot, " .
            "EXISTS (SELECT 1 FROM person_position WHERE person_id = p.id AND position_id = ?) AS has_sanctuary " .
            "FROM person p) t1 WHERE has_dirt_green_dot != has_sanctuary ORDER BY callsign",
            [Position::DIRT_GREEN_DOT, Position::SANCTUARY]
        );
    }

    public static function repair($peopleIds, ...$options): array
    {
        $results = [];

        foreach ($peopleIds as $personId) {
            $messages = [];
            $errors = [];

            $hasDirt = PersonPosition::havePosition($personId, Position::DIRT_GREEN_DOT);
            $hasSanctuary = PersonPosition::havePosition($personId, Position::SANCTUARY);

            if (!$hasDirt || !$hasSanctuary) {
                $positionIds = [];

                if (!$hasDirt) {
                    $positionIds[] = Position::DIRT_GREEN_DOT;
                    $messages[] = 'added Dirt - Green Dot';
                }

                if (!$hasSanctuary) {
                    $positionIds[] = Position::SANCTUARY;
                    $messages[] = 'added Sanctuary';
                }
                PersonPosition::addIdsToPerson($personId, $positionIds, 'position sanity checker repair');
            }

            $result = [
                'id' => $personId,
                'messages' => $messages
            ];

            if (!empty($errors)) {
                $result['errors'] = $errors;
            }

            $results[] = $result;
        }

        return $results;
    }
}
