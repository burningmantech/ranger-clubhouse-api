<?php

use Faker\Generator as Faker;
use App\Models\PersonMessage;

$factory->define(PersonMessage::class, function (Faker $faker) {
    return [
        'subject'           => $faker->realText($faker->numberBetween(10,15)),
        'message_from'      => $faker->firstName,
        'body'              => $faker->realText($faker->numberBetween(20,100)),
        'creator_person_id' => 1,
    ];
});
