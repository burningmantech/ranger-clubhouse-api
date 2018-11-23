<?php

namespace App\Models;

use Carbon\Carbon;

use App\Models\ApiModel;
use DB;

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
        'credits_per_hour' => 'float'
    ];

    protected $dates = [
        'start_time',
        'end_time'
    ];

    static public $yearCache = [];

    public $start_timestamp;
    public $end_timestamp;

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

        foreach ($rows as $row) {
            // Cache the timestamp converstion
            $row->start_timestamp = $row->start_time->timestamp;
            $row->end_timestamp = $row->end_time->timestamp;
        }

        self::$yearCache[$year][$positionId] = $rows;

        return $rows;
    }

    public static function warmYearCache($year, $positionIds) {
        $year = intval($year);
        $rows = self::whereIn('position_id', $positionIds)
                ->whereYear('start_time', $year)
                ->whereYear('end_time', $year)
                ->orderBy('start_time')->get();

        foreach ($rows as $row) {
            // Cache the timestamp converstion
            $row->start_timestamp = $row->start_time->timestamp;
            $row->end_timestamp = $row->end_time->timestamp;
        }

        self::$yearCache[$year] = $rows->groupBy('position_id');

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
     * @param integer $startTime the starting time of the shift
     * @param integer $endTime the ending time of the shift
     * @param array $creditCache (optional) the position credits for the year
     * @return float the earn credits
     */
    public static function computeCredits(int $positionId, int $startTime, int $endTime, int $year): float {
        $credits = PositionCredit::findForYear($year, $positionId);

        if (empty($credits)) {
            return 0.0;
        }

        $total = 0.0;

        foreach ($credits as $credit) {
            $minutes = self::minutesOverlap($startTime, $endTime, $credit->start_timestamp, $credit->end_timestamp);

            if ($minutes > 0) {
                $total += $minutes * $credit->credits_per_hour / 60.0;
            }
        }

        return $total;
    }

    public static function minutesOverlap(int $startA, int $endA, int $startB, int $endB): float {
        // latest start time
        $start = $startA > $startB ? $startA : $startB;
        // earlies end time
        $ending = $endA > $endB ? $endB : $endA;

        if ($start >= $ending) {
            return 0; # no overlap
        }

        return round(($ending - $start) / 60.0);
    }
}
