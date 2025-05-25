<?php

namespace App\Lib\Reports;

use App\Models\PersonTeam;
use App\Models\PositionCredit;
use App\Models\TeamManager;
use App\Models\Timesheet;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TimesheetByCallsignReport
{
    /**
     * Retrieve all timesheets for a given year, grouped by callsign
     *
     * @param int $year
     * @return array
     */

    public static function execute(int $year): array
    {
        $user = Auth::user();

        $sql = Timesheet::whereYear('on_duty', $year)
            ->with([
                'person:id,callsign,status',
                'position:id,title,type,count_hours,active',
                'slot'
            ])->orderBy('on_duty');

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

        $rows = $sql->get();

        if (!$rows->isEmpty()) {
            PositionCredit::warmYearCache($year, array_unique($rows->pluck('position_id')->toArray()));
        }

        $personGroups = $rows->groupBy('person_id');

        $callsigns = $personGroups->map(function ($group) {
            $person = $group[0]->person;

            return [
                'id' => $group[0]->person_id,
                'callsign' => $person->callsign ?? "Person #" . $group[0]->person_id,
                'status' => $person->status ?? 'deleted',

                'total_credits' => $group->pluck('credits')->sum(),
                'total_duration' => $group->pluck('duration')->sum(),
                'total_appreciation_duration' => $group->filter(fn($t) => $t->position?->count_hours ?? false)
                    ->pluck('duration')->sum(),

                'timesheet' => $group->map(function ($t) {
                    if ($t->slot_id && $t->slot) {
                        $assoc = $t->slot;
                        $slot = [
                            'id' => $t->slot_id,
                            'description' => $assoc->description,
                            'begins' => (string)$assoc->begins,
                            'duration' => $assoc->duration,
                            'timezone' => $assoc->timezone,
                            'timezone_abbr' => $assoc->timezone_abbr,
                        ];
                    } else {
                        $slot = null;
                    }
                    return [
                        'id' => $t->id,
                        'position_id' => $t->position_id,
                        'on_duty' => (string)$t->on_duty,
                        'off_duty' => (string)$t->off_duty,
                        'duration' => $t->duration,
                        'credits' => $t->credits,
                        'slot' => $slot
                    ];
                })->values()
            ];
        })->sortBy('callsign', SORT_NATURAL | SORT_FLAG_CASE)->values()->toArray();

        $positions = [];
        foreach ($rows as $row) {
            $position = $row->position;
            $positions[$row->position_id] ??= [
                'id' => $row->position_id,
                'title' => $position->title ?? "Position #" . $row->position_id,
                'count_hours' => $position->count_hours ?? 0,
                'active' => $position->active ?? false,
            ];
        }

        return [
            'status' => $user->isAdmin() ? 'full-report' : 'partial-report',
            'people' => $callsigns,
            'positions' => $positions,
        ];
    }

    public static function noTeamsOrPositionsResult(): array
    {
        return [
            'status' => 'no-membership',
            'people' => [],
            'positions' => [],
        ];
    }
}
