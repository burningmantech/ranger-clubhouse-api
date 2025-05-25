<?php

namespace App\Lib\Reports;

use App\Models\PersonTeam;
use App\Models\PositionCredit;
use App\Models\TeamManager;
use App\Models\Timesheet;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Psr\SimpleCache\InvalidArgumentException;

class TimesheetByPositionReport
{
    /**
     * Breakdown the positions within a given year
     *
     * @param int $year
     * @param bool $includeEmail
     * @return array
     * @throws InvalidArgumentException
     */

    public static function execute(int $year, bool $includeEmail = false): array
    {
        $sql = Timesheet::whereYear('on_duty', $year)
            ->with(['person:id,callsign,status,email', 'position:id,title,active', 'slot'])
            ->orderBy('on_duty');

        $user = Auth::user();
        if (!$user->isAdmin()) {
            // Need to filter based on what cadre / delegations (not teams) the person belongs to
            // and what teams they are a Clubhouse Team Manager for.
            $opsMemberIds = PersonTeam::retrieveCadreMembershipIds($user->id);
            $managerIds = TeamManager::retrieveTeamIdsForPerson($user->id);

            $teamIds = [...$opsMemberIds, ...$managerIds];

            if (empty($teamIds)) {
                return self::noTeamsOrPositionsResult();
            }

            $positionIds = DB::table('position')
                ->whereIn('team_id', $teamIds)
                ->pluck('id');

            if ($positionIds->isEmpty()) {
                return self::noTeamsOrPositionsResult();
            }

            $sql->whereIn('position_id', $positionIds);
        }
        $rows = $sql->get()->groupBy('position_id');

        $positions = [];
        $people = [];

        if ($rows->isNotEmpty()) {
            PositionCredit::warmBulkYearCache([$year => $rows->keys()]);
        }

        foreach ($rows as $positionId => $entries) {
            $position = $entries[0]->position;
            $positions[] = [
                'id' => $position->id,
                'title' => $position->title,
                'active' => $position->active,
                'timesheets' => $entries->map(function ($r) use ($includeEmail, & $people) {
                    $person = $r->person;
                    $people[$r->person_id] ??= [
                        'id' => $r->person_id,
                        'callsign' => $person->callsign ?? 'Person #' . $r->person_id,
                        'status' => $person->status ?? 'deleted'
                    ];

                    if ($includeEmail) {
                        $people[$r->person_id]['email'] ??= $person->email ?? '';
                    }

                    if ($r->slot_id && $r->slot) {
                        $assoc = $r->slot;
                        $slot = [
                            'id' => $r->slot_id,
                            'description' => $assoc->description,
                            'begins' =>(string) $assoc->begins,
                            'duration' => $assoc->duration,
                            'timezone' => $assoc->timezone,
                            'timezone_abbr' => $assoc->timezone_abbr,
                        ];
                    } else {
                        $slot = null;
                    }

                    return [
                        'id' => $r->id,
                        'on_duty' => (string)$r->on_duty,
                        'off_duty' => (string)$r->off_duty,
                        'duration' => $r->duration,
                        'person_id' => $r->person_id,
                        'credits' => $r->credits,
                        'slot' => $slot,
                    ];
                })
            ];
        }

        usort($positions, fn($a, $b) => strcasecmp($a['title'], $b['title']));

        return [
            'status' => $user->isAdmin() ? 'full-report' : 'partial-report',
            'positions' => $positions,
            'people' => $people,
        ];
    }

    public static function noTeamsOrPositionsResult(): array
    {
        return [
            'status' => 'no-membership',
            'positions' => [],
            'people' => [],
        ];
    }
}