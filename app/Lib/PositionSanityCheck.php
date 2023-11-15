<?php

namespace App\Lib;

use InvalidArgumentException;

class PositionSanityCheck
{
    /*
     * Report on problematic position assignments and roles
     *
     * "STOP THE INSANITY" -- Susan Powders, 1990s self-proclaimed exercise "guru" & peroxide enthusiast.
     */

    const CHECKERS = [
        'deactivated_accounts' => 'App\Lib\PositionSanityCheck\DeactivatedAccounts',
        'deactivated_positions' => 'App\Lib\PositionSanityCheck\DeactivatedPositionCheck',
        'deactivated_teams' => 'App\Lib\PositionSanityCheck\DeactivatedTeamsCheck',
        'lmyr' => 'App\Lib\PositionSanityCheck\LoginManagementYearRoundCheck',
        'missing_positions' => 'App\Lib\PositionSanityCheck\MissingPositionsCheck',
        'retired_accounts' => 'App\Lib\PositionSanityCheck\RetiredAccounts',
        'shiny_pennies' => 'App\Lib\PositionSanityCheck\ShinnyPenniesCheck',
        'team_membership' => 'App\Lib\PositionSanityCheck\TeamMembershipCheck',
        'team_positions' => 'App\Lib\PositionSanityCheck\TeamPositionsCheck',
    ];

    public static function issues(): array
    {
        foreach (self::CHECKERS as $name => $checker) {
            $insanity[$name] = call_user_func("$checker::issues");
        }

        $insanity['shiny_penny_year'] = current_year();

        return $insanity;
    }

    /**
     * Repair position / role problems
     *
     * @param string $repair - the name of the thing to repair 'green_dot', 'management_role', 'shiny_penny'
     * @param array $peopleIds - list of person ids to repair. Ids are assumed to exist.
     * @param array $options
     * @return array
     */

    public static function repair(string $repair, array $peopleIds, array $options): array
    {
        if (!array_key_exists($repair, self::CHECKERS)) {
            throw new InvalidArgumentException("Unknown repair action [$repair]");
        }

        $class = self::CHECKERS[$repair];
        return call_user_func("$class::repair", $peopleIds, $options);
    }

}
