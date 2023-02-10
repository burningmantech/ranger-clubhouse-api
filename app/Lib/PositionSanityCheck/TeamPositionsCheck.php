<?php

namespace App\Lib\PositionSanityCheck;

use App\Models\PersonPosition;
use App\Models\Position;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TeamPositionsCheck extends SanityCheck
{
    /**
     * Find people who are missing team positions
     *
     * @return array
     */

    public static function issues(): array
    {
        $rows = self::retrieveMissingPositions();

        $issues = [];
        foreach ($rows as $person) {
            $issues[] = [
                'id' => $person->id,
                'callsign' => $person->callsign,
                'status' => $person->status,
                'team_id' => $person->team_id,
                'team_title' => $person->team_title,
                'position_id' => $person->position_id,
                'position_title' => $person->position_title,
            ];
        }

        return $issues;
    }

    /**
     * Add missing team positions to the given people
     *
     * @param $peopleIds
     * @param ...$options
     * @return array
     */

    public static function repair($peopleIds, ...$options): array
    {
        $results = [];
        $positions = $options[0];
        foreach ($peopleIds as $personId) {
            $positionIds = $positions[$personId] ?? null;
            if (!$positionIds) {
                throw new InvalidArgumentException("Person #{$personId} was not found in the options");
            }
            PersonPosition::addIdsToPerson($personId, $positionIds, 'position sanity checker repair - missing team positions');
            foreach ($positionIds as $pid) {
                $results[] = [
                    'id' => $personId,
                    'position_id' => $pid,
                    'messages' => ['added position']
                ];
            }
        }
        return $results;
    }

    /**
     * Retrieve folks who belong to a team(s) and are missing team positions
     *
     * @param array $peopleIds
     * @return Collection
     */

    public static function retrieveMissingPositions(array $peopleIds = []): Collection
    {
        $sql = DB::table('team')
            ->select(
                'person.id',
                'person.callsign',
                'person.status',
                'team.id as team_id',
                'team.title as team_title',
                'position.id as position_id',
                'position.title as position_title'
            )
            ->join('person_team', 'person_team.team_id', 'team.id')
            ->join('person', 'person.id', 'person_team.person_id')
            ->join('position', function ($j) {
                $j->on('position.team_id', 'team.id');
                $j->where('position.team_category', Position::TEAM_CATEGORY_ALL_MEMBERS);
                $j->where('position.active', true);
            })->leftJoin('person_position', function ($j) {
                $j->on('person_position.position_id', 'position.id');
                $j->whereColumn('person_position.person_id', 'person.id');
            })->whereNull('person_position.position_id')
            ->orderBy('person.callsign')
            ->orderBy('team.title')
            ->orderBy('position.title');

        if (!empty($peopleIds)) {
            $sql->whereIntegerInRaw('person.id', $peopleIds);
        }

        return $sql->get();
    }
}
