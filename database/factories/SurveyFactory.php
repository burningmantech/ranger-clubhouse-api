<?php

namespace Database\Factories;

use App\Models\Survey;
use Illuminate\Database\Eloquent\Factories\Factory;

class SurveyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => $this->faker->text(20),
            'type' => Survey::TRAINING,
            'year' => date('Y'),
            'prologue' => $this->faker->text(),
            'epilogue' => $this->faker->text(),
        ];
    }
}