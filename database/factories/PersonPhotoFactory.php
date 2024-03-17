<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PersonPhotoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'image_filename' => 'http://localhost/photo.png',
            'width' => 100,
            'height' => 100,

            'orig_filename' => 'http://localhost/photo.png',
            'orig_width' => 100,
            'orig_height' => 100,
        ];
    }
}
