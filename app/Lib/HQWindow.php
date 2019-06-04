<?php

namespace App\Lib;

use App\Models\Position;
use App\Models\Slot;

use Illuminate\Support\Facades\DB;

class HQWindow
{
    public static function retrieveCheckInOutForecast($year, $interval=15)
    {
        // All shift visits excluding Training, Trainer, TiT, Uber & Burn Perimeter
        $hqPositions = [ Position::HQ_WINDOW, Position::HQ_RUNNER, Position::HQ_SHORT, Position::HQ_LEAD ];

        $intervalSeconds = $interval * 60;

        // Find out when HQ is supposedly open
        $row = DB::table('slot')
                ->selectRaw("FROM_UNIXTIME($intervalSeconds*floor(unix_timestamp(begins)/$intervalSeconds)) as opening")
                ->whereIn('position_id', $hqPositions)
                ->whereYear('begins', $year)
                ->orderBy('begins')
                ->first();

        // No opening slot? Usually happen early in the year when no shifts are setup.
        if ($row == null) {
            return [
                'visits'=> [],
                'burns' => []
            ];
        }

        $hqStart = $row->opening;

        $roundUp = $intervalSeconds - 1;

        $row = DB::table('slot')
                ->selectRaw("FROM_UNIXTIME($intervalSeconds*floor((unix_timestamp(ends) + $roundUp)/$intervalSeconds)) as closing")
                ->whereIn('position_id', $hqPositions)
                ->whereYear('begins', $year)
                ->orderBy('ends', 'desc')
                ->first();
        $hqEnd = $row->closing;

        // Find all the checkins except all training related positions
        $allVisits = self::findAllHQVisits($hqStart, $hqEnd, $intervalSeconds);

        // Find all the Burn Perimeter slots for the given year
        $burnPerimeterSlots = Slot::where('position_id', Position::BURN_PERIMETER)
                                ->whereYear('begins', $year)
                                ->orderBy('begins')
                                ->get();
        $burnsByTime = [];

        foreach ($burnPerimeterSlots as $slot) {
            $begins = (string) $slot->begins;

            if (!array_key_exists($begins, $burnsByTime)) {
                $burnsByTime[$begins] = [
                    'descriptions' => [],
                    'visits'       => self::findBurnPerimeterVisits($begins, $allVisits, $intervalSeconds)
                ];
            }
            $burnsByTime[$begins]['descriptions'][] = $slot->description;
        }

        // Remove the key and place the period time into the array
        $visits = [];
        foreach ($allVisits as $period => $visit) {
            $visit['period'] = $period;
            $visits[] = $visit;
        }

        $burns = array_values($burnsByTime);

        return [
            'visits'=> $visits,
            'burns' => $burns
        ];
    }

    /*
     * Find all HQ visits for a given year
     *
     * Associated array list is formatted a
     * key is time period 'YYYY-mm-dd HH:MM:00'
     *  'checkin'   - expected check in visits
     *  'checkout'  - expected check out visits
     *  'windows'   - HQ Window workers count
     *  'runners'   - HQ Runners count
     *  'shorts'    - HQ Short count
     *  'leads'     - HQ Lead count
     *
     */

    public static function findAllHQVisits($start, $end, $intervalSeconds)
    {
        $dtStart = \DateTime::createFromFormat('Y-m-d H:i:s', $start);
        $dtEnd = \DateTime::createFromFormat('Y-m-d H:i:s', $end);

        $intervalMinutes = $intervalSeconds / 60;
        $interval = \DateInterval::createFromDateString("$intervalMinutes minutes");
        $period = new \DatePeriod($dtStart, $interval, $dtEnd);

        $rows = [];
        $periods = [];

        foreach ($period as $dt) {
            $dtStart = $dt->format("Y-m-d H:i:00");
            $periods[$dtStart] = [
                'checkin'   => 0,
                'checkout'  => 0,
                'windows'   => 0,
                'runners'   => 0,
                'shorts'    => 0,
                'leads'     => 0
            ];
        }

        $cond  = 'slot.position_id NOT IN ('.implode(',', [ Position::TRAINING, Position::TRAINER, Position::TRAINER_UBER, Position::TRAINER_ASSOCIATE]).')';
        $cond = "slot.position_id NOT IN (".implode(',', [ Position::HQ_WINDOW, Position::HQ_RUNNER, Position::HQ_SHORT, Position::HQ_LEAD ]).") AND $cond AND begins >= CAST('$start' AS DATETIME) AND ends <=  CAST('$end' AS DATETIME)";
        $checkins = self::findHQVisits('begins', $cond, $intervalSeconds);
        $checkouts = self::findHQVisits('ends', $cond, $intervalSeconds);

        self::populateForecastColumn($checkins, $periods, 'checkin');
        self::populateForecastColumn($checkouts, $periods, 'checkout');

        $staffing = [
             [ 'windows', Position::HQ_WINDOW ],
             [ 'runners', Position::HQ_RUNNER ],
             [ 'shorts', Position::HQ_SHORT ],
             [ 'leads', Position::HQ_LEAD ],
        ];

        // Find the staffing check in and outs for the various HQ positions, and build up a schedule.
        foreach ($staffing as $staff) {
            $column = $staff[0];
            $positionId = $staff[1];

            $cond = "slot.position_id=$positionId AND (begins BETWEEN CAST('$start' AS DATETIME) AND CAST('$end' AS DATETIME) OR ends BETWEEN CAST('$start' AS DATETIME) AND CAST('$end' AS DATETIME))";

            $shiftStart = self::findHQVisits('begins', $cond, $intervalSeconds);
            $shiftEnd = self::findHQVisits('ends', $cond, $intervalSeconds);

            self::populateForecastColumn($shiftStart, $periods, $column);
            self::populateForecastShiftEnd($shiftEnd, $periods, $column);
        }

        return $periods;
    }

    /*
     * Find the check in & outs for (all) Burn Perimeters starting and ending
     * within a given time.
     *
     */

    public static function findBurnPerimeterVisits($start, $periods, $intervalSeconds)
    {
        $cond = "position_id=".Position::BURN_PERIMETER." AND begins='$start'";
        $checkins = self::findHQVisits('begins', $cond, $intervalSeconds);
        $checkouts = self::findHQVisits('ends', $cond, $intervalSeconds);

        $visits = [];
        foreach ($checkins as $row) {
            $time = (string)$row->time;

            if (!array_key_exists($time, $periods)) {
                $visits[$time] = [
                    'checkin'   => (int)$row->total,
                    'checkout'  => 0,
                    'windows'   => 0,
                    'runners'   => 0,
                    'shorts'    => 0,
                    'leads'     => 0,
                ];
            } else {
                $period = $periods[$time];
                $visits[$time] = [
                    'checkin'   => (int)$row->total,
                    'checkout'  => 0,
                    'windows'   => (int)$period['windows'],
                    'runners'   => (int)$period['runners'],
                    'shorts'    => (int)$period['shorts'],
                    'leads'     => (int)$period['leads'],
                ];
            }
        }

        foreach ($checkouts as $row) {
            $time = (string)$row->time;
            if (!array_key_exists($time, $periods)) {
                $visits[$time] = [
                    'checkin'  => 0,
                    'checkout' => (int)$row->total,
                    'windows'  => 0,
                    'runners'  => 0,
                    'shorts'   => 0,
                    'leads'    => 0,
                ];
            } else {
                $period = $periods[$time];
                $visits[$time] = [
                    'checkin'  => 0,
                    'checkout' => (int)$row->total,
                    'windows'  => (int)$period['windows'],
                    'runners'  => (int)$period['runners'],
                    'shorts'   => (int)$period['shorts'],
                    'leads'    => (int)$period['leads'],
                ];
            }
        }

        $results = [];

        foreach ($visits as $period => $visit) {
            $visit['period'] = $period;
            $results[] = $visit;
        }

        return $results;
    }

    /*
     * Find all the expected HQ visits (aka shift start or ends)
     */

    public static function findHQVisits($column, $cond, $intervalSeconds = 900)
    {
        return DB::table('slot')
                ->selectRaw("from_unixtime($intervalSeconds*floor(unix_timestamp($column)/$intervalSeconds)) as 'time'")
                ->selectRaw('sum(signed_up) as total')
                ->whereRaw($cond)
                ->groupBy('time')
                ->orderBy('time')
                ->get();
    }

    /*
     * Loop thru a possible check in/out list and set the given column to the count.
     */

    public static function populateForecastColumn($rows, &$periods, $column)
    {
        foreach ($rows as $row) {
            $time = $row->time;
            if (array_key_exists($time, $periods)) {
                $periods[$time][$column] = (int)$row->total;
            }
        }
    }

    /*
     * Loop thru and update the shift end counts by subtracting
     */

    public static function populateForecastShiftEnd($rows, &$periods, $column)
    {
        $subtract = [];
        foreach ($rows as $row) {
            $time = $row->time;
            if (array_key_exists($time, $periods)) {
                $subtract[$time] = (int)$row->total;
            }
        }

        $total = 0;
        foreach ($periods as $time => $period) {
            $total += $period[$column];
            if (isset($subtract[$time])) {
                $total -= $subtract[$time];
            }

            $periods[$time][$column] = $total;
        }
    }
}
