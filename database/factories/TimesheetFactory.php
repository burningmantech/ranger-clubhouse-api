<?php

use Faker\Generator as Faker;
use App\Models\Timesheet;

$factory->define(Timesheet::class, function (Faker $faker) {
    return [
        'position_id'   => 2,
        'on_duty'       => date('Y-08-25 18:00:00'),
        'off_duty'      => date('Y-08-25 19:00:00'),
    ];
});
