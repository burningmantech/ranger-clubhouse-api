<?php

namespace App\Lib;

//TODO Remove these?
use App\Models\Person;
use App\Models\Position;
use App\Models\PersonPosition;
use App\Models\Role;
use App\Models\PersonRole;
use App\Models\PersonMentor;

use Illuminate\Support\Facades\DB;

class PositionSanityCheck
{
    /*
     * Report on problematic position assignments and roles
     *
     * "STOP THE INSANITY" -- Susan Powders, 1990s self proclaimed exercise "guru"
     *  & peroxide enthusiast.
     */

    // TODO Have these implement an abstract class?
    const CHECKERS = [
        'green_dot'       => 'App\Lib\PositionSanityCheck\GreenDotCheck',
        'management_role' => 'App\Lib\PositionSanityCheck\ManagementCheck',
        'shiny_pennies'   => 'App\Lib\PositionSanityCheck\ShinnyPenniesCheck',
    ];

    public static function issues(): array
    {
        foreach(self::CHECKERS as $name => $checker) {
            $insanity[$name] = $checker::issues();
        }

        $insanity['shiny_penny_year'] = current_year();

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
