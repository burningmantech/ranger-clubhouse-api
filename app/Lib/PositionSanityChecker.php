<?php

namespace App\Lib;

use App\Models\Person;
use App\Models\Position;
use App\Models\PersonPosition;
use App\Models\Role;
use App\Models\PersonRole;
use App\Models\PersonMentor;

use Illuminate\Support\Facades\DB;

class PositionSanityChecker
{
    /*
     * Report on problematic position assignments and roles
     *
     * "STOP THE INSANITY" -- Susan Powders, 1990s self proclaimed exercise "guru"
     *  & peroxide enthusiast.
     */

    public static function sanityChecker(): array
    {
        $year = current_year();

        $insanity['green_dot'] = DB::select(
            "SELECT * FROM (SELECT p.id AS id, callsign, status, " .
            "EXISTS (SELECT 1 FROM person_position WHERE person_id = p.id AND position_id = ?) AS has_dirt_green_dot, " .
            "EXISTS (SELECT 1 FROM person_position WHERE person_id = p.id AND position_id = ?) AS has_sanctuary, " .
            "EXISTS (SELECT 1 FROM person_position WHERE person_id = p.id AND position_id = ?) AS has_gp_gd " .
            "FROM person p) t1 WHERE has_dirt_green_dot != has_sanctuary OR has_dirt_green_dot != has_gp_gd ORDER BY callsign",
            [Position::DIRT_GREEN_DOT, Position::SANCTUARY, Position::GERLACH_PATROL_GREEN_DOT]
        );

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

        $insanity['management_role'] = [];
        foreach ($rows as $row) {
            $positions = Position::select('id', 'title')->whereIn('id', $row->person_position->pluck('position_id'))->orderBy('title')->get();

            $insanity['management_role'][] = [
                'id' => $row->id,
                'callsign' => $row->callsign,
                'status' => $row->status,
                'is_shiny_penny' => $row->is_shiny_penny,
                'positions' => $positions
            ];
        }

        $insanity['shiny_pennies'] = DB::select(
            "SELECT * FROM (SELECT p.id AS id, callsign, status, year, " .
            "EXISTS(SELECT 1 FROM person_position WHERE person_id = p.id AND position_id = ?) AS has_shiny_penny " .
            "FROM person p INNER JOIN " .
            "(SELECT person_id, MAX(mentor_year) as year FROM person_mentor " .
            "  WHERE status = 'pass' GROUP BY person_id) pm " .
            "ON pm.person_id = p.id) t1 " .
            "WHERE (NOT has_shiny_penny AND year = $year) OR (has_shiny_penny AND year != $year) " .
            "ORDER BY year desc, callsign",
            [Position::DIRT_SHINY_PENNY]
        );

        $insanity['shiny_penny_year'] = $year;

        return $insanity;
    }

    /**
     * Repair position / role problems
     *
     * @param string $repair - the name of the thing to repair 'green-dot', 'management-role', 'shiny-penny'
     * @param array $peopleIds - list of person ids to repair. Ids are assumed to exist.
     * @return array
     */

    public static function repair(string $repair, array $peopleIds): array
    {
        $results = [];

        switch ($repair) {
            case 'green-dot':
                foreach ($peopleIds as $personId) {
                    $messages = [];
                    $errors = [];

                    $hasDirt = PersonPosition::havePosition($personId, Position::DIRT_GREEN_DOT);
                    $hasSanctuary = PersonPosition::havePosition($personId, Position::SANCTUARY);
                    $hasGPGD = PersonPosition::havePosition($personId, Position::GERLACH_PATROL_GREEN_DOT);

                    if (!$hasDirt && !$hasSanctuary) {
                        $errors[] = 'not a Green Dot';
                    } else {
                        $positionIds = [];

                        if (!$hasDirt) {
                            $positionIds[] = Position::DIRT_GREEN_DOT;
                            $messages[] = 'added Dirt - Green Dot';
                        }

                        if (!$hasSanctuary) {
                            $positionIds[] = Position::SANCTUARY;
                            $messages[] = 'added Sanctuary';
                        }
                        if (!$hasGPGD) {
                            $positionIds[] = Position::GERLACH_PATROL_GREEN_DOT;
                            $messages[] = 'added Gerlach Patrol - Green Dot';
                        }
                        PersonPosition::addIdsToPerson($personId, $positionIds, 'position sanity checker repair');
                    }

                    $result = [
                        'id' => $personId,
                        'messages' => $messages
                    ];

                    if (!empty($errors)) {
                        $result['errors'] = $errors;
                    }

                    $results[] = $result;
                }
                return $results;

            case 'management-role':
                foreach ($peopleIds as $personId) {
                    PersonRole::addIdsToPerson($personId, [Role::MANAGE], 'position sanity checker repair');
                    $results[] = ['id' => $personId];
                }
                return $results;

            case 'shiny-penny':
                $year = current_year();

                foreach ($peopleIds as $personId) {
                    $hasPenny = PersonPosition::havePosition($personId, Position::DIRT_SHINY_PENNY);
                    $isPenny = PersonMentor::retrieveYearPassed($personId) == $year;

                    if ($hasPenny && !$isPenny) {
                        PersonPosition::removeIdsFromPerson($personId, [Position::DIRT_SHINY_PENNY], 'position sanity checker repair');
                        $message = 'not a Shiny Penny, position removed';
                    } elseif (!$hasPenny && $isPenny) {
                        PersonPosition::addIdsToPerson($personId, [Position::DIRT_SHINY_PENNY], 'position sanity checker repair');
                        $message = 'is a Shiny Penny, position added';
                    } else {
                        $message = 'Shiny Penny already has position. no repair needed.';
                    }
                    $results[] = ['id' => $personId, 'messages' => [$message]];
                }
                return $results;

            default:
                throw new InvalidArgumentException("Unknown repair action [$repair]");
        }
    }

}
