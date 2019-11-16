<?php

use Faker\Generator as Faker;
use App\Models\TrainerStatus;

$factory->define(TrainerStatus::class, function (Faker $faker) {
    return [
        'status'   => TrainerStatus::ATTENDED
    ];
});
