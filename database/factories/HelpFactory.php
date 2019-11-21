<?php

use Faker\Generator as Faker;
use App\Models\Help;
use Carbon\Carbon;
use Illuminate\Support\Str;

$factory->define(Help::class, function (Faker $faker) {
    return [
        'slug'  => Str::random(10),
        'title' => $faker->uuid,
        'body'  => $faker->text(10),
        'tags'  => '',
    ];
});
