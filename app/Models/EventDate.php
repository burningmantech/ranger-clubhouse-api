<?php

namespace App\Models;

use App\Models\ApiModel;

use Carbon\Carbon;

class EventDate extends ApiModel
{
    protected $table = 'event_dates';
    protected $auditModel = true;

    // All other table names are singular, and this one had to be pural. deal with it.
    protected $resourceSingle = 'event_date';

    protected $fillable = [
        'event_end',
        'event_start',
        'post_event_end',
        'pre_event_slot_end',
        'pre_event_slot_start',
        'pre_event_start'
    ];

    protected $dates = [
        'event_end',
        'event_start',
        'post_event_end',
        'pre_event_slot_end',
        'pre_event_slot_start',
        'pre_event_start'
    ];

    protected $rules = [
        'event_end'            => 'required|date',
        'event_start'          => 'required|date',
        'post_event_end'       => 'required|date',
        'pre_event_slot_end'   => 'required|date',
        'pre_event_slot_start' => 'required|date',
        'pre_event_start'      => 'required|date',
    ];

    public static function findAll() {
        return self::orderBy('event_start')->get();
    }

    public static function findForYear($year) {
        return self::whereYear('event_start', $year)->first();
    }

    /**
     * Calculate what event period we're in.
     *
     * - before-event: start of the year til before gate opening
     * - event: the event week from gate opening til end of Labor Day
     * - after-event: Tuesday after Labor Day til the end of the year.
     *
     * @return string
     */
    public static function calculatePeriod() {
        $year = current_year();
        $now = now();

        $laborDay = (new Carbon("September $year first monday"))->setTime(23,59,59);

        if ($laborDay->lte($now)) {
            return 'after-event';
        }

        $gateOpen = $laborDay->clone()->subDays(8)->setTime(0,0,1);
        if ($gateOpen->lte($now) && $laborDay->gte($now)) {
            return 'event';
        }

        return 'before-event';
    }
}
