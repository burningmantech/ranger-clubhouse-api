<?php

namespace App\Lib\PositionSanityCheck;

use App\Models\Position;

use Illuminate\Support\Facades\DB;

class GreenDotCheck extends SanityCheck
{
    public static function issues(): array
    {
        return  DB::select(
            "SELECT * FROM (SELECT p.id AS id, callsign, status, " .
            "EXISTS (SELECT 1 FROM person_position WHERE person_id = p.id AND position_id = ?) AS has_dirt_green_dot, " .
            "EXISTS (SELECT 1 FROM person_position WHERE person_id = p.id AND position_id = ?) AS has_sanctuary, " .
            "EXISTS (SELECT 1 FROM person_position WHERE person_id = p.id AND position_id = ?) AS has_gp_gd " .
            "FROM person p) t1 WHERE has_dirt_green_dot != has_sanctuary OR has_dirt_green_dot != has_gp_gd ORDER BY callsign",
            [Position::DIRT_GREEN_DOT, Position::SANCTUARY, Position::GERLACH_PATROL_GREEN_DOT]
        );
    }
}
