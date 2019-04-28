<?php

use Faker\Generator as Faker;
use App\Models\TimesheetMissing;

$factory->define(TimesheetMissing::class, function (Faker $faker) {
    return [ 'partner' => '' ];
});
