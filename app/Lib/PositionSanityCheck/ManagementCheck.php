<?php

namespace App\Lib\PositionSanityCheck;

use App\Models\Position;
use App\Models\Person;
use App\Models\PersonPosition;
use App\Models\Role;
use App\Models\PersonRole;

use Illuminate\Support\Facades\DB;

class ManagementCheck extends SanityCheck
{
    public static function issues(): array
    {
        $year = current_year();

        // All HQ, Operators, Shift Leads, etc. should have the "Management Mode" role
        $positionIds = [
            Position::GREEN_DOT_LEAD_INTERN,
            Position::GREEN_DOT_LEAD,
            Position::HQ_LEAD,
            Position::HQ_SHORT,
            Position::HQ_WINDOW,
            Position::INTERCEPT_DISPATCH,
            Position::MENTOR_LEAD,
            Position::OPERATOR,
            Position::PERSONNEL_INVESTIGATOR,
            Position::QUARTERMASTER,
            Position::RSC_SHIFT_LEAD_PRE_EVENT,
            Position::RSC_SHIFT_LEAD,
            Position::RSC_WESL,
            Position::RSCI_MENTEE,
            Position::RSCI,
            Position::TECH_ON_CALL,
            Position::TRAINER,
        ];

        $personIds = PersonPosition::whereIn('position_id', $positionIds)
            ->groupBy('person_id')
            ->pluck('person_id');

        $rows = Person::select('id', 'callsign', 'status', DB::raw("EXISTS (SELECT 1 FROM timesheet WHERE YEAR(on_duty)=$year AND person_id=person.id AND position_id=" . Position::ALPHA . " LIMIT 1) AS is_shiny_penny"))
            ->whereIn('id', $personIds)
            ->whereIn('status', [Person::ACTIVE, Person::INACTIVE, Person::INACTIVE_EXTENSION])
            ->whereRaw('NOT EXISTS (SELECT 1 FROM person_role WHERE person_role.person_id=person.id AND person_role.role_id=?)', [Role::MANAGE])
            ->orderBy('callsign')
            ->with(['person_position' => function ($q) use ($positionIds) {
                $q->whereIn('position_id', $positionIds);
            }])
            ->get();

        $issues = [];
        foreach ($rows as $row) {
            $positions = Position::select('id', 'title')->whereIn('id', $row->person_position->pluck('position_id'))->orderBy('title')->get();

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

    public static function repair($peopleIds): array
    {
        $results = [];
        foreach ($peopleIds as $personId) {
            PersonRole::addIdsToPerson($personId, [Role::MANAGE], 'position sanity checker repair');
            $results[] = ['id' => $personId];
        }
        return $results;
    }
}
