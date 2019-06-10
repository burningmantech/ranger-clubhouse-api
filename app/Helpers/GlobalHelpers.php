<?php
/*
 * Global Helpers used everywhere throughout the application.
 */

use App\Models\Setting;
use App\Helpers\SqlHelper;

if (!function_exists('setting')) {
 function setting($name)
 {
     return Setting::get($name);
 }
}

/*
 * Support for groundhog day server
 *
 * When GroundhogDayServer is true, use the database year.
 * otherwise use the system year.
 */

if (!function_exists('current_year')) {
 function current_year()
 {
     static $year;

     if (config('clubhouse.GroundhogDayServer')) {
         if ($year) {
             return $year;
         }

         $year = SqlHelper::now()->year;
         return $year;
     }
     return date('Y');
 }
}
