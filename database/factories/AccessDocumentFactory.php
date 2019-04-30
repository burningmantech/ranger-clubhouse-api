<?php

use Faker\Generator as Faker;
use App\Models\AccessDocument;

$factory->define(AccessDocument::class, function (Faker $faker) {
    return [
        'create_date' => date('Y-m-d H:i:s'),
    ];
});
