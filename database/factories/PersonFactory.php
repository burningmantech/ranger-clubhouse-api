<?php

use Faker\Generator as Faker;
use App\Models\Person;
use Carbon\Carbon;

$factory->define(App\Models\Person::class, function (Faker $faker) {
    $salt = "0123467890123456789";
    return [
        'status'        => 'active',
        'callsign'      => 'callsign '.str_random(5),
        'callsign_approved' => true,
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
        'create_date'   => Carbon::now()->format('Y-m-d H:i:s'),
        'bpguid'        => $faker->uuid,
    ];
});
