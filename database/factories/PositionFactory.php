<?php

namespace Database\Factories;

use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

class PositionFactory extends Factory
{
    protected $model = Position::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->unique()->word(),
            'max' => 1,
            'min' => 0,
            'active' => true,
            'all_team_members' => false,
            'public_team_position' => false,
            'role_ids' => null,
        ];
    }
}
