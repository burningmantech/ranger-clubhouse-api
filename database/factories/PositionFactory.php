<?php

use Faker\Generator as Faker;

use App\Models\Position;

$factory->define(Position::class, function (Faker $faker) {
    return [
        'title' => $faker->text(10),
        'max'   => 1,
        'min'   => 0
    ];
});
