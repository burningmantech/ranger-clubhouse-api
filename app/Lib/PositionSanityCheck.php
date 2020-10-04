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
     * @param string $repair - the name of the thing to repair 'green_dot', 'management_role', 'shiny_penny'
     * @param array $peopleIds - list of person ids to repair. Ids are assumed to exist.
     * @return array
     */

    public static function repair(string $repair, array $peopleIds): array
    {
        if (!array_key_exists($repair, self::CHECKERS)) {
            throw new InvalidArgumentException("Unknown repair action [$repair]");
        }

        return self::CHECKERS[$repair]::repair($peopleIds);
    }

}
