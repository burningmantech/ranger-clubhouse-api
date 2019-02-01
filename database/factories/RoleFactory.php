<?php

use Faker\Generator as Faker;
use App\Models\Role;

$factory->define(Role::class, function (Faker $faker) {
    return [
        'title'  => $faker->name,
    ];
});
