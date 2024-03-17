<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PersonEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'year' => current_year(),
        ];
    }
}
