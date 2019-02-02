<?php

use Faker\Generator as Faker;

use App\Models\Slot;

$factory->define(Slot::class, function (Faker $faker) {
    return [
        'active'    => true,
        'min'       => 1,
        'max'       => 10,
        'description'   => 'slot',
        'signed_up' => 0,
    ];
});
