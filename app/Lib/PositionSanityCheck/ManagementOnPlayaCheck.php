<?php

namespace App\Lib\PositionSanityCheck;

use App\Models\Position;
use App\Models\Role;

class ManagementOnPlayaCheck extends SanityCheck
{
    const ON_PLAYA_POSITIONS = [
        Position::GREEN_DOT_LEAD_INTERN,
        Position::GREEN_DOT_LEAD,
        Position::HQ_LEAD,
        Position::HQ_SHORT,
        Position::HQ_WINDOW,
        Position::INTERCEPT_DISPATCH,
        Position::MENTOR_LEAD,
        Position::OPERATOR,
        Position::QUARTERMASTER,
        Position::RSC_SHIFT_LEAD_PRE_EVENT,
        Position::RSC_SHIFT_LEAD,
        Position::RSC_WESL,
        Position::RSCI_MENTEE,
        Position::RSCI,
        Position::TECH_ON_CALL,
    ];

    public static function issues(): array
    {
        return ManagementCommon::issues(self::ON_PLAYA_POSITIONS, Role::MANAGE_ON_PLAYA, Role::MANAGE);
    }

    public static function repair($peopleIds, ...$options): array
    {
        return ManagementCommon::repair($peopleIds, Role::MANAGE_ON_PLAYA);
    }
}
