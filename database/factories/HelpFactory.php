<?php

use Faker\Generator as Faker;
use App\Models\Help;
use Carbon\Carbon;

$factory->define(Help::class, function (Faker $faker) {
    return [
        'slug'  => str_random(10),
        'title' => $faker->uuid,
        'body'  => $faker->text(10),
        'tags'  => '',
    ];
});
