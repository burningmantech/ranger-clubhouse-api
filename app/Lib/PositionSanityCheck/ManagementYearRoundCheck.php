<?php

namespace App\Lib\PositionSanityCheck;

use App\Models\Position;
use App\Models\Role;

class ManagementYearRoundCheck extends SanityCheck
{
    const YEAR_ROUND_POSITIONS = [
        Position::PERSONNEL_INVESTIGATOR,
        Position::TRAINER,
    ];

    public static function issues(): array
    {
        return ManagementCommon::issues(self::YEAR_ROUND_POSITIONS, Role::MANAGE);
    }

    public static function repair($peopleIds, ...$options): array
    {
        return ManagementCommon::repair($peopleIds, Role::MANAGE);
    }
}
