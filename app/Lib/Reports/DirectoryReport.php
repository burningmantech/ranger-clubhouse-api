<?php

namespace App\Lib\Reports;

use App\Models\Team;
use Illuminate\Support\Facades\DB;

class DirectoryReport
{
    /**
     * Return a list of all cadres & delegations with email contacts.
     *
     * @return array
     */

    const int COUNCIL_CADRE = 6;    // Hate hardcoding values.

    public static function execute(): array
    {
        $teams = Team::whereIn('team.type', [Team::TYPE_CADRE, Team::TYPE_DELEGATION])
            ->where('team.active', true)
            ->orderBy('team.title')
            ->with(['members', 'members.person_photo'])
            ->get();

        $directory = [];
        $council = null;
        foreach ($teams as $team) {
            if ($team->members->isEmpty()) {
                continue;
            }

            $overseerPositions = DB::table('person_position')
                ->select('position.id', 'position.title', 'person_position.person_id')
                ->join('position', 'position.id', 'person_position.position_id')
                ->whereIn('person_position.person_id', $team->members->pluck('id'))
                ->where('position.team_id', $team->id)
                ->where('position.active', true)
                ->where(function ($w) {
                    $w->where('position.title', 'like', '%manager')
                        // At some point fix this -- supervisors are typically under the team not cadre/delegation
                        ->orWhere('position.title', 'like', '%supervisor');
                })->orderBy('position.title')
                ->get();

            $overseerPositions = $overseerPositions->groupBy('person_id');

            $cadre = [
                'id' => $team->id,
                'title' => $team->title,
                'email' => $team->email,
                'description' => $team->description,
                'members' => $team->members->map(fn($member): array => [
                    'id' => $member->id,
                    'callsign' => $member->callsign,
                    'profile_url' => $member->person_photo?->profileUrlApproved(),
                    'overseer_positions' => $overseerPositions
                        ->get($member->id)
                        ?->map(fn($position) => [
                            'id' => $position->id,
                            'title' => $position->title
                        ])->toArray()
                ])->toArray(),
            ];

            if ($team->id == self::COUNCIL_CADRE) {
                $council = $cadre;
            } else {
                $directory[] = $cadre;
            }
        }

        if ($council) {
            array_unshift($directory, $council);
        }

        return $directory;
    }


}