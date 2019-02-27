<?php

use Faker\Generator as Faker;
use App\Models\PersonMentor;

$factory->define(PersonMentor::class, function (Faker $faker) {
    return [
        // TODO FIX THE CASE ON THIS DAMN COLUMN!!!
        'STATUS' => 'pending',
    ];
});
