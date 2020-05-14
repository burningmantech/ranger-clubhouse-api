<?php

use Faker\Generator as Faker;
use App\Models\Person;
use Carbon\Carbon;
use Illuminate\Support\Str;

$factory->define(App\Models\Person::class, function (Faker $faker) {
    $salt = "0123467890123456789";
    $uuid = (string) Str::uuid();
    return [
        'status'        => 'active',
        'callsign'      => $uuid,
        'callsign_approved' => true,
        'email'         => $uuid.'@e.co',
        'first_name'    => 'Bravo',
        'last_name'     => 'Delta',
        'street1'       => '1 Main Street',
        'city'          => 'Smallville',
        'state'         => 'CA',
        'country'       => 'USA',
        'zip'           => '94501',
        'home_phone'    => '415-555-1212',
        'alt_phone'     => '',
        'password'      => $salt.":".sha1($salt."ineedashower!"),
        'create_date'   => '2019-01-01 00:00:00',
        'bpguid'        => $uuid,
        'behavioral_agreement' => true,
        'on_site'       => false,
        'has_reviewed_pi' => true
    ];
});
