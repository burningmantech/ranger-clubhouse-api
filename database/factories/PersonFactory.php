<?php

use Faker\Generator as Faker;
use App\Models\Person;

$factory->define(App\Models\Person::class, function (Faker $faker) {
    $salt = "0123467890123456789";
    return [
        'callsign'      => 'callsign '.str_random(5),
        'email'         => $faker->unique()->safeEmail,
        'first_name'    => $faker->firstName,
        'last_name'     => $faker->lastName,
        'street1'       => $faker->streetAddress,
        'state'         => 'CA',
        'country'       => 'USA',
        'zip'           => $faker->postcode,
        'home_phone'    => $faker->phoneNumber,
        'password'      => $salt.":".sha1($salt."ineedashower!"),
        'user_authorized' => true,
        'status'        => 'active',
        'create_date'   => now(),
    ];
});
