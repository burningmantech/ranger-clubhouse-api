<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PersonMentorFactory extends Factory
{

    public function definition(): array
    {
        return [
            'status' => 'pending',
        ];
    }
}
