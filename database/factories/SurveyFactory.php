<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Survey;

class SurveyFactory extends Factory
{
    protected $model = Survey::class;

    public function definition()
    {
       return [
            'title' => $this->faker->text(20),
            'type' => Survey::TRAINING,
            'year' => date('Y'),
            'prologue' => $this->faker->text,
            'epilogue' => $this->faker->text,
        ];
    }
}