<?php

namespace App\Lib\PositionSanityCheck;

use App\Models\Person;
use App\Models\PersonPosition;
use App\Models\PersonTeam;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use App\Exceptions\UnacceptableConditionException;

class DeactivatedTeamsCheck
{
    public static function issues(): array
    {
        $teams = DB::table('team')
            ->where('active', false)
            ->whereRaw("EXISTS (SELECT 1 FROM person_team WHERE person_team.team_id=team.id LIMIT 1)")
            ->orderBy('title')
            ->get();

        $results = [];
        foreach ($teams as $team) {
            $results[] = [
                'id' => $team->id,
                'title' => $team->title,
                'people' => DB::table('person_team')
                    ->select('person.id', 'person.callsign', 'person.status', 'person_team.team_id')
                    ->join('person', 'person.id', 'person_team.person_id')
                    ->whereNotIn('person.status', Person::DEACTIVATED_STATUSES)
                    ->where('person_team.team_id', $team->id)
                    ->orderBy('callsign')
                    ->get()
            ];
        }

        return $results;
    }

    public static function repair($peopleIds, ...$options): array
    {
        $results = [];
        $teamId = $options[0]['teamId'];

        // Validate team exists and is inactive
        $team = Team::find($teamId);
        if (!$team) {
            throw new UnacceptableConditionException("Invalid team id [$teamId]!");
        }

        if ($team->active) {
            throw new UnacceptableConditionException("Team id=[$teamId] title=[{$team->title}] is active");
        }

        $positionIds = DB::table('position')->where('team_id', $team->id)->get()->pluck('id')->toArray();
        if (!empty($positionIds)) {
            foreach ($peopleIds as $personId) {
                PersonPosition::removeIdsFromPerson($personId, $positionIds, 'team deactivation repair');
            }
        }


        foreach ($peopleIds as $personId) {
            PersonTeam::removePerson($teamId, $personId, 'team deactivation repair');

            $results[] = [
                'id' => $personId,
                'messages' => ['team removed']
            ];
        }

        return $results;
    }
}