<?php

namespace App\Lib\PositionSanityCheck;

use App\Models\PersonRole;
use App\Models\Role;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EventManagementYearRoundCheck extends SanityCheck
{
    /**
     * Find people who have the Login Management Year Round Role but do not belong to
     * a team which grants the role.
     *
     * @return array
     */

    public static function issues(): array
    {
        $people = self::retrievePeople();

        $issues = [];
        foreach ($people as $person) {
            $issues[] = [
                'id' => $person->id,
                'callsign' => $person->callsign,
                'status' => $person->status
            ];
        }

        return $issues;
    }

    /**
     * Revoke the Event Management Year Round Role from selected individuals
     *
     * @param $peopleIds
     * @param ...$options
     * @return array
     */

    public static function repair($peopleIds, ...$options): array
    {
        $rows = self::retrievePeople($peopleIds);
        $results = [];

        foreach ($rows as $person) {
            $personId = $person->id;
            PersonRole::removeIdsFromPerson($personId, [Role::EVENT_MANAGEMENT], 'position sanity checker repair - no Event Management Year Round position');
            $results[] = ['id' => $personId];
        }

        return $results;
    }

    /**
     * Retrieve folks who were manually assigned the EMYR Role but do not hold
     * any position which grants EMYR.
     *
     * @param array $peopleIds
     * @return Collection
     */

    public static function retrievePeople(array $peopleIds = []): Collection
    {
        if (empty($peopleIds)) {
            $peopleIds = DB::table('person_role')
                ->where('person_role.role_id', Role::EVENT_MANAGEMENT)
                ->get('person_id')
                ->pluck('person_id');
        }

        if (empty($peopleIds)) {
            return collect([]);
        }

        $positionIds = DB::table('position_role')
            ->select('position_role.position_id')
            ->where('position_role.role_id', Role::EVENT_MANAGEMENT)
            ->get()
            ->pluck('position_id')
            ->toArray();

        $teamIds = DB::table('team_role')
            ->select('team_role.team_id')
            ->where('team_role.role_id', Role::EVENT_MANAGEMENT)
            ->get()
            ->pluck('team_id')
            ->toArray();

        $sql = DB::table('person')
            ->select(
                'person.id',
                'person.callsign',
                'person.status',
            )->whereIn('person.id', $peopleIds);

        if (!empty($positionIds)) {
            $sql->leftJoin('person_position', function ($j) use ($positionIds) {
                $j->on('person_position.person_id', 'person.id');
                $j->whereIn('person_position.position_id', $positionIds);
            });
            $sql->whereNull('person_position.position_id');
        }

        if (!empty($teamIds)) {
            $sql->leftJoin('person_team', function ($j) use ($teamIds) {
                $j->on('person_team.person_id', 'person.id');
                $j->whereIn('person_team.team_id', $teamIds);
            });
            $sql->whereNull('person_team.team_id');
        }

        return $sql->orderBy('callsign')->get();
    }
}
