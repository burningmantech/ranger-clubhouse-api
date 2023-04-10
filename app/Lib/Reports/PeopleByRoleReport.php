<?php

namespace App\Lib\Reports;

use App\Models\Person;
use App\Models\Role;
use App\Models\Training;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PeopleByRoleReport
{
    /**
     * Report on all assigned Clubhouse roles.
     *
     * @param bool $explicitGrants
     * @return array
     */

    public static function execute(bool $explicitGrants = false): array
    {
        $rows = DB::table('role')
            ->orderBy('title')
            ->get();

        $year = current_year();

        $roles = [];

        $isAdmin = Auth::user()?->isAdmin();

        foreach ($rows as $role) {
            $roleId = $role->id;
            if ($roleId == Role::PAYROLL && !$isAdmin) {
                // Hide payroll role from non-admins.
                continue;
            }

            $roleGrants = DB::table('person_role')
                ->select('person.id', 'person.callsign', 'person.status')
                ->join('person', 'person.id', 'person_role.person_id')
                ->where('role_id', $roleId)
                ->get();

            $positionGrants = DB::table('position_role')
                ->select('person.id', 'person.callsign', 'person.status',
                    'position.id as position_id', 'position.title as position_title',
                    'position.require_training_for_roles', 'position.training_position_id')
                ->join('position', 'position.id', 'position_role.position_id')
                ->join('person_position', 'person_position.position_id', 'position_role.position_id')
                ->join('person', 'person.id', 'person_position.person_id')
                ->where('role_id', $roleId)
                ->where('position.active', true)
                ->orderBy('position.title')
                ->get();

            if ($positionGrants->isNotEmpty()) {
                $peopleByIds = Person::whereIntegerInRaw('id', $positionGrants->pluck('id')->unique()->toArray())->get()->keyBy('id');
            } else {
                $peopleByIds = collect([]);
            }

            $teamGrants = DB::table('team_role')
                ->select('person.id', 'person.callsign', 'person.status', 'team.id as team_id', 'team.title as team_title')
                ->join('team', 'team.id', 'team_role.team_id')
                ->join('person_team', 'person_team.team_id', 'team_role.team_id')
                ->join('person', 'person.id', 'person_team.person_id')
                ->where('role_id', $roleId)
                ->where('team.active', true)
                ->orderBy('team.title')
                ->get();

            // Combine folks.
            $people = [];
            self::mergePeople($people, $roleGrants, fn($person) => $person->granted = true);

            self::mergePeople($people, $positionGrants,
                function ($person, $row) use ($peopleByIds, $year) {
                    $position = [
                        'id' => $row->position_id,
                        'title' => $row->position_title,
                    ];

                    if ($row->require_training_for_roles && $row->training_position_id) {
                        $position['require_training_for_roles'] = true;
                        $position['is_trained'] = Training::didPersonPassForYear($peopleByIds->get($row->id), $row->training_position_id, $year);
                    }
                    $person->positions[] = $position;
                }
            );

            self::mergePeople($people, $teamGrants,
                fn($person, $row) => $person->teams[] = [
                    'id' => $row->team_id,
                    'title' => $row->team_title
                ]
            );

            $people = array_values($people);
            usort($people, fn($a, $b) => strcasecmp($a->callsign, $b->callsign));

            $roles[] = [
                'id' => $role->id,
                'title' => $role->title,
                'people' => $people,
            ];
        }

        return $roles;
    }

    /**
     * Merge the $rows folks into the $people
     *
     * @param $people
     * @param $rows
     * @param callable $entityCallback
     */

    public
    static function mergePeople(&$people, $rows, callable $entityCallback): void
    {
        foreach ($rows as $row) {
            $personId = $row->id;
            if (!isset($people[$personId])) {
                $people[$personId] = (object)[
                    'id' => $personId,
                    'callsign' => $row->callsign,
                    'status' => $row->status,
                    'teams' => [],
                    'positions' => []
                ];
            }

            $entityCallback($people[$personId], $row);
        }
    }
}