<?php

namespace App\Lib\PositionSanityCheck;

use App\Models\Position;

use Illuminate\Support\Facades\DB;

class ShinnyPenniesCheck extends SanityCheck
{
    public static function issues(...$options): array
    {
        $year = current_year();

        return DB::select(
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
    }
}
