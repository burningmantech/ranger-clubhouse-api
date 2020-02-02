<?php

use Faker\Generator as Faker;
use App\Models\PersonPhoto;

$factory->define(PersonPhoto::class, function (Faker $faker) {
    return [
        'image_filename' => 'http://localhost/photo.png',
        'width' => 100,
        'height' => 100,

        'orig_filename' => 'http://localhost/photo.png',
        'orig_width' => 100,
        'orig_height' => 100,
    ];
});
