<?php

namespace App\Helpers;

use Carbon\Carbon;

class DateHelper
{
    public static function formatDate($date): string
    {
        return Carbon::parse($date)->format('Y/m/d');
    }

    public static function formatShift($time): string
    {
        // return as Mon Sep 01 @ 05:45
        return Carbon::parse($time)->format('D M d @ H:i');
    }

    public static function formatDateTime($time): string
    {
        if (is_integer($time)) {
            $time = Carbon::createFromTimestamp($time);
        }

        return $time->format('D M j Y @ H:i');

    }
}
