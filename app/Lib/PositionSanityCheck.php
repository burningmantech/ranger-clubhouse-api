<?php

namespace App\Lib;

use App\Exceptions\UnacceptableConditionException;
use App\Lib\PositionSanityCheck\DeactivatedPositionCheck;

class PositionSanityCheck
{
    /*
     * Report on problematic position assignments and roles
     *
     * "STOP THE INSANITY" -- Susan Powders, 1990s self-proclaimed exercise "guru" & peroxide enthusiast.
     */

    const array CHECKERS = [
        'deactivated_accounts' => PositionSanityCheck\DeactivatedAccounts::class,
        'deactivated_positions' => DeactivatedPositionCheck::class,
        'deactivated_teams' => PositionSanityCheck\DeactivatedTeamsCheck::class,
        'emop' => PositionSanityCheck\EventManagementYearRoundCheck::class,
        'missing_positions' => PositionSanityCheck\MissingPositionsCheck::class,
        'retired_accounts' => PositionSanityCheck\RetiredAccounts::class,
        'shiny_pennies' => PositionSanityCheck\ShinnyPenniesCheck::class,
        'team_membership' => PositionSanityCheck\TeamMembershipCheck::class,
        'team_positions' => PositionSanityCheck\TeamPositionsCheck::class,
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
            throw new UnacceptableConditionException("Unknown repair action [$repair]");
        }

        $class = self::CHECKERS[$repair];
        return call_user_func("$class::repair", $peopleIds, $options);
    }

}
