<?php

namespace App\Lib\PositionSanityCheck;

use App\Models\Person;
use App\Models\PersonPosition;
use App\Models\PersonRole;
use App\Models\Position;
use Illuminate\Support\Facades\DB;

class ManagementCommon
{
    public static function issues($positionIds, $roleId, $ignoreId = null): array
    {
        $year = current_year();
        $personIds = PersonPosition::whereIntegerInRaw('position_id', $positionIds)
            ->groupBy('person_id')
            ->pluck('person_id');

        $sql = Person::select('id', 'callsign', 'status', DB::raw("EXISTS (SELECT 1 FROM timesheet WHERE YEAR(on_duty)=$year AND person_id=person.id AND position_id=" . Position::ALPHA . " LIMIT 1) AS is_shiny_penny"))
            ->whereIntegerInRaw('id', $personIds)
            ->whereIn('status', [Person::ACTIVE, Person::INACTIVE, Person::INACTIVE_EXTENSION])
            ->whereRaw('NOT EXISTS (SELECT 1 FROM person_role WHERE person_role.person_id=person.id AND person_role.role_id=?)', [$roleId])
            ->orderBy('callsign')
            ->with(['person_position' => function ($q) use ($positionIds) {
                $q->whereIntegerInRaw('position_id', $positionIds);
            }]);

        if ($ignoreId) {
            $sql->whereRaw('NOT EXISTS (SELECT 1 FROM person_role WHERE person_role.person_id=person.id AND person_role.role_id=?)', [$ignoreId]);
        }

        $rows = $sql->get();

        $issues = [];
        foreach ($rows as $row) {
            $positions = Position::select('id', 'title')->whereIntegerInRaw('id', $row->person_position->pluck('position_id'))->orderBy('title')->get();

            $issues[] = [
                'id' => $row->id,
                'callsign' => $row->callsign,
                'status' => $row->status,
                'is_shiny_penny' => $row->is_shiny_penny,
                'positions' => $positions
            ];
        }

        return $issues;
    }

    public static function repair($peopleIds, $roleId): array
    {
        $results = [];
        foreach ($peopleIds as $personId) {
            PersonRole::addIdsToPerson($personId, [$roleId], 'position sanity checker repair');
            $results[] = ['id' => $personId];
        }
        return $results;
    }
}
