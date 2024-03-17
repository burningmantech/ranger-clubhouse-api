<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TraineeStatusFactory extends Factory
{
    public function definition(): array
    {
        return [
            'passed' => false,
        ];
    }
}
