<?php
/*
 * Global Helpers used everywhere throughout the application.
 */

 use App\Models\Setting;

 if (! function_exists('setting')) {
     function setting($name) {
         return Setting::get($name);
     }
 }
 
