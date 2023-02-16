<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class EventDate extends ApiModel
{
    protected $table = 'event_dates';
    protected $auditModel = true;

    /**
     * Event periods throughout the year wrt the {Ranger,PNV,Auditor} dashboards.
     * (not to be confuse with the {pre,post}-event weeks.)
     */
    const AFTER_EVENT = 'after-event';
    const BEFORE_EVENT = 'before-event';
    const EVENT = 'event';

    protected $resourceSingle = 'event_date';

    protected $fillable = [
        'event_end',
        'event_start',
        'post_event_end',
        'pre_event_slot_end',
        'pre_event_slot_start',
        'pre_event_start'
    ];

    protected $casts = [
        'event_end' => 'datetime',
        'event_start' => 'datetime',
        'post_event_end' => 'datetime',
        'pre_event_slot_end' => 'datetime',
        'pre_event_slot_start' => 'datetime',
        'pre_event_start' => 'datetime',
    ];

    protected $rules = [
        'event_end' => 'required|date',
        'event_start' => 'required|date',
        'post_event_end' => 'required|date',
        'pre_event_slot_end' => 'required|date',
        'pre_event_slot_start' => 'required|date',
        'pre_event_start' => 'required|date',
    ];

    public static function findAll(): Collection
    {
        return self::orderBy('event_start')->get();
    }

    public static function findForYear(int $year): ?EventDate
    {
        return self::whereYear('event_start', $year)->first();
    }

    /**
     * Calculate what the event period is for the dashboard.
     *
     * - before-event: March 1st til gate opening
     * - event: gate opening til Saturday @ noon after Labor Day.
     * - after-event:  Saturday @ noon after Labor Day til March 1st
     *
     * @return string
     */
    public static function calculatePeriod(): string
    {
        $periodSetting = setting('DashboardPeriod');
        if (!empty($periodSetting) && $periodSetting != 'auto') {
            return $periodSetting;
        }

        $now = now();
        if ($now->month < 3) {
            return self::AFTER_EVENT;
        }

        $year = $now->year;

        $laborDay = new Carbon("September $year first monday");

        // Figure out when the Saturday after Labor Day is
        $finalSaturday = $laborDay->clone()->addDays(5)->setTime(12, 0, 0);
        if ($finalSaturday->lte($now)) {
            return self::AFTER_EVENT;
        }

        // Gate open is the Sunday a week before Labor Day
        $gateOpen = $laborDay->clone()->subDays(8)->setTime(0, 0, 0);
        if ($gateOpen->lte($now)) {
            return self::EVENT;
        }

        return self::BEFORE_EVENT;
    }

    /**
     * Retrieve the burn weekend date range.
     *
     * @return array start and ending time
     */

    public static function retrieveBurnWeekendPeriod(): array
    {
        $year = current_year();
        $laborDay = new Carbon("September $year first monday");
        // Burn weekend is Friday @ 18:00 til Monday @ 00:00 (late Sunday night)
        $start = $laborDay->clone()->subDays(3)->setTime(18, 0, 0);
        $end = $laborDay->clone()->setTime(0, 0, 0);

        return [$start, $end];
    }
}
