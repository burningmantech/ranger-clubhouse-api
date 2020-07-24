<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Models\PersonEvent;
use Faker\Generator as Faker;

$factory->define(PersonEvent::class, function (Faker $faker) {
    return [
        'year' => current_year(),
    ];
});
