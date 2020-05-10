<?php

namespace App\Models;

use App\Models\ApiModel;
use Illuminate\Support\Facades\DB;
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
}
