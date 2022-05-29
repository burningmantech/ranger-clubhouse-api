<?php

namespace Database\Factories;

use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

class PositionFactory extends Factory
{
    protected $model = Position::class;

    public function definition()
    {
        return [
            'title' => $this->faker->unique()->word(),
            'max' => 1,
            'min' => 0,
            'active' => true
        ];
    }
}
