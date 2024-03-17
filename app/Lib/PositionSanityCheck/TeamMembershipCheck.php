<?php

namespace App\Lib\PositionSanityCheck;

use App\Models\Person;
use App\Models\PersonPosition;
use App\Models\PersonTeam;
use App\Models\Position;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use App\Exceptions\UnacceptableConditionException;

class TeamMembershipCheck extends SanityCheck
{
    /**
     * Find people who have team positions but do not belong to a team
     *
     * @return array
     */

    public static function issues(): array
    {
        return self::retrieveMissingTeams();
    }

    /**
     * Add missing teams to the given people
     *
     * @param $peopleIds
     * @param ...$options
     * @return array
     */

    public static function repair($peopleIds, ...$options): array
    {
        $rows = self::retrieveMissingTeams($peopleIds);
        $results = [];
        $teams = $options[0];
        foreach ($rows as $person) {
            $personId = $person->id;
            $teamIds = $teams[$personId] ?? null;
            if (!$teamIds) {
                throw new UnacceptableConditionException("Person #{$personId} was not found in the options");
            }
            foreach ($teamIds as $tid) {
                PersonTeam::addPerson($tid, $personId, 'position sanity checker repair - add missing team');
                $results[] = ['id' => $personId, 'team_id' => $tid];
            }
        }

        return $results;
    }

    /**
     * Retrieve folks who have (non-public) team positions.
     *
     * @param array $peopleIds
     * @return array
     */

    public static function retrieveMissingTeams(array $peopleIds = []): array
    {
        $teams = Team::findAll();

        $peopleTeams = [];
        foreach ($teams as $team) {
            $positionIds = DB::table('position')
                ->select('id')
                ->where('team_id', $team->id)
                ->where('team_category', Position::TEAM_CATEGORY_ALL_MEMBERS)
                ->get()
                ->pluck('id')
                ->toArray();

            if (empty($positionIds)) {
                // Team has no team positions
                continue;
            }

            // Find people who hold default (basic) team positions yet are not team members
            $sql = DB::table('person_position')
                ->select(
                    'person.id',
                    'person.callsign',
                    'person.status',
                    'position.id as position_id',
                    'position.title as position_title'
                )->join('person', 'person.id', 'person_position.person_id')
                ->join('position', 'position.id', 'person_position.position_id')
                ->leftJoin('person_team', function ($j) use ($team) {
                    $j->on('person_team.person_id', 'person_position.person_id');
                    $j->where('person_team.team_id', $team->id);
                })
                ->whereNotIn('person.status', Person::DEACTIVATED_STATUSES)
                ->whereNotNull('position.team_id')
                ->whereIn('person_position.position_id', $positionIds)
                ->whereNull('person_team.person_id');

            if (!empty($peopleIds)) {
                $sql->whereIn('person_position.person_id', $peopleIds);
            }
            $rows = $sql->get();

            $people = [];
            foreach ($rows as $person) {
                $personId = $person->id;
                $people[$personId] ??= (object)[
                    'id' => $personId,
                    'callsign' => $person->callsign,
                    'status' => $person->status,
                    'team_id' => $team->id,
                    'team_title' => $team->title,
                    'positions' => []
                ];

                $people[$personId]->positions[] = [
                    'id' => $person->position_id,
                    'title' => $person->position_title,
                ];
            }

            foreach ($people as $personId => $person) {
                $peopleTeams[] = $person;
            }
        }

        usort($peopleTeams, fn($a, $b) => strcasecmp($a->callsign, $b->callsign) ?: strcasecmp($a->team_title, $b->team_title));

        return $peopleTeams;
    }
}
