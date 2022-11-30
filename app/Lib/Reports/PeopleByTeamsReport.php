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
        $teams->load('members:person.id,callsign,status,is_manager');

        return $teams->map(function ($team) {
            $members = $team->members->map(fn($member) => [
                'id' => $member->id,
                'callsign' => $member->callsign,
                'status' => $member->status,
                'is_manager' => $member->is_manager,
            ])->toArray();

            usort($members, fn($a, $b) => strcasecmp($a['callsign'], $b['callsign']));

            return [
                'id' => $team->id,
                'title' => $team->title,
                'members' => $members,
            ];
        })->toArray();
    }
}