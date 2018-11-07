<?php

namespace App\Models;

use Carbon\Carbon;

use App\Models\ApiModel;

class PositionCredit extends ApiModel
{
    protected $table = 'position_credit';

    protected $fillable = [
        'position_id',
        'start_time',
        'end_time',
        'credits_per_hour',
        'description',
    ];

    protected $casts = [
        'start_time'       => 'datetime',
        'end_time'         => 'datetime',
        'credits_per_hour' => 'float'
    ];

    static public $yearCache = [];

    /*
     * Find all the credits for a given year
     *
     * @param integer $year The year to search
     * @return array PositionCredits
     */

    public static function findForYear($year, $positionId) {
        $year = intval($year);
        $positionId = intval($positionId);

        if (isset(self::$yearCache[$year][$positionId])) {
            return self::$yearCache[$year][$positionId];
        }

        $rows = self::where('position_id', $positionId)
                ->whereYear('start_time', $year)
                ->whereYear('end_time', $year)
                ->orderBy('start_time')->get();

        self::$yearCache[$year][$positionId] = $rows;

        return $rows;
    }

    public static function warmYearCache($year, $positionIds) {
        $year = intval($year);
        self::$yearCache[$year] = self::whereIn('position_id', $positionIds)
                ->whereYear('start_time', $year)
                ->whereYear('end_time', $year)
                ->orderBy('start_time')->get()->groupBy('position_id');

        foreach ($positionIds as $id) {
            if (!isset(self::$yearCache[$year][$id])) {
                self::$yearCache[$year][$id] = [];
            }
        }
    }

    /*
     * Compute the credits for a position given the start and end times
     *
     * @param integer $positionId the id of the position
     * @param Carbon $startTime the starting time of the shift
     * @param Carbon $endTime the ending time of the shift
     * @param array $creditCache (optional) the position credits for the year
     * @return float the earn credits
     */
    public static function computeCredits($positionId, $startTime, $endTime): float {
        $startTime = Carbon::parse($startTime);
        $endTime = Carbon::parse($endTime);

        $positions = PositionCredit::findForYear($startTime->year, $positionId);

        if (empty($positions)) {
            return 0.0;
        }

        $credits = 0.0;

        foreach ($positions as $position) {
            $minutes = self::minutesOverlap($startTime, $endTime, $position->start_time, $position->end_time);

            if ($minutes > 0) {
                $credits += floatval($minutes) * (floatval($position->credits_per_hour) / 60.0);
            }
        }

        return round($credits, 2);
    }

    public static function minutesOverlap($startA, $endA, $startB, $endB) {
        // latest start time
        $start = $startA->gt($startB) ? $startA : $startB;
        // earlies end time
        $ending = $endA->gt($endB) ? $endB : $endA;

        if ($start->gte($ending)) {
            return 0; # no overlap
        }

        return ($ending->diffInMinutes($start));
    }
}
