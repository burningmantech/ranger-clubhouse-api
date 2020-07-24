<?php

use Faker\Generator as Faker;
use App\Models\Vehicle;

$factory->define(Vehicle::class, function (Faker $faker) {
    $year = date('Y');
    return [
        'person_id' => 99999,
        'license_number' => $faker->text(10),
        'license_state' => 'CA',
        'event_year' => $year,
        'type' => 'personal',
        'vehicle_year' => $year,
        'vehicle_make' => $faker->text(10),
        'vehicle_model' => $faker->text(10),
        'vehicle_color' => $faker->text(10)
    ];
});
