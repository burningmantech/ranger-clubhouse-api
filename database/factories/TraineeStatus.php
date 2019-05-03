<?php

use Faker\Generator as Faker;
use App\Models\TraineeStatus;

$factory->define(TraineeStatus::class, function (Faker $faker) {
    return [
        'passed'   => false,
    ];
});
