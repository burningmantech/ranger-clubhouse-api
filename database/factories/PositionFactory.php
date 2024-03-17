<?php

namespace Database\Factories;

use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

class PositionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => $this->faker->unique()->word(),
            'max' => 1,
            'min' => 0,
            'active' => true,
            'team_category' => Position::TEAM_CATEGORY_PUBLIC,
            'role_ids' => null,
        ];
    }
}
