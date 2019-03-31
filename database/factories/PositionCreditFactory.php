<?php

use Faker\Generator as Faker;
use App\Models\PositionCredit;

$factory->define(PositionCredit::class, function (Faker $faker) {
    return [
        'position_id'   => 1,
        'credits_per_hour'  => 1.00,
        'description'   => $faker->text(20),
    ];
});
