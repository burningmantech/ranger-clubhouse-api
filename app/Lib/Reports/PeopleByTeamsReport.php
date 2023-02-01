<?php

namespace App\Lib\Reports;

use App\Models\Team;

class PeopleByTeamsReport
{
    /**
     * Report on all teams and their members.
     *
     * @return array
     */

    public static function execute(): array
    {
        $teams = Team::findAll();
        $teams->load(['members:person.id,callsign,status', 'managers']);

        return $teams->map(function ($team) {
            return [
                'id' => $team->id,
                'title' => $team->title,
                'managers' => $team->managers->map(fn($manager) => [
                    'id' => $manager->id,
                    'callsign' => $manager->callsign,
                    'status' => $manager->status,
                ])->toArray(),
                'members' => $team->members->map(fn($member) => [
                    'id' => $member->id,
                    'callsign' => $member->callsign,
                    'status' => $member->status,
                ])->toArray(),
            ];
        })->toArray();
    }
}