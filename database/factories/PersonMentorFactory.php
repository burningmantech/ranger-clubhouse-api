<?php

use Faker\Generator as Faker;
use App\Models\PersonMentor;

$factory->define(PersonMentor::class, function (Faker $faker) {
    return [
        'status' => 'pending',
    ];
});
