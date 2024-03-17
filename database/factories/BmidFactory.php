<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class BmidFactory extends Factory
{
    public function definition(): array
    {
        return [
            'status' => 'in_prep',
            'showers' => false,
            'meals' => null,
        ];
    }
}
