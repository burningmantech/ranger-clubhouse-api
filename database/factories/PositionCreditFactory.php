<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;


class PositionCreditFactory extends Factory
{
    public function definition(): array
    {
        return [
            'position_id' => 1,
            'credits_per_hour' => 1.00,
            'description' => $this->faker->text(20),
        ];
    }
}
