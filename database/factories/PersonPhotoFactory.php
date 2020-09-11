<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\PersonPhoto;

class PersonPhotoFactory extends Factory
{
    protected $model = PersonPhoto::class;

    public function definition()
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
