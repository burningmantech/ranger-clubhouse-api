<?php

namespace App\Lib\Reports;

use Illuminate\Support\Facades\DB;

class PeopleByRoleReport
{
    /**
     * Report on all assigned Clubhouse roles.
     *
     * @return array
     */

    public static function execute(): array
    {
        $roleGroups = DB::table('role')
            ->select('role.id as role_id', 'role.title', 'person.id as person_id', 'person.callsign')
            ->join('person_role', 'person_role.role_id', 'role.id')
            ->join('person', 'person.id', 'person_role.person_id')
            ->orderBy('callsign')
            ->get()
            ->groupBy('role_id');

        $roles = [];
        foreach ($roleGroups as $roleId => $group) {
            $roles[] = [
                'id' => $roleId,
                'title' => $group[0]->title,
                'people' => $group->map(function ($row) {
                    return ['id' => $row->person_id, 'callsign' => $row->callsign];
                })->values()
            ];
        }

        usort($roles, fn($a, $b) => strcasecmp($a['title'], $b['title']));

        return $roles;
    }
}