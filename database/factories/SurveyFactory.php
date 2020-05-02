<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Models\Survey;
use Faker\Generator as Faker;

$factory->define(Survey::class, function (Faker $faker) {
    return [
        'title' => $faker->text(20),
        'type' => Survey::TRAINING,
        'year' => date('Y'),
        'prologue' => $faker->text,
        'epilogue' => $faker->text,
    ];
});
