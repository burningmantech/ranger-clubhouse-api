<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\SurveyQuestion;

class SurveyQuestionFactory extends Factory
{
    protected $model = SurveyQuestion::class;

    public function definition()
    {
        return [
            'survey_id' => 1,
            'survey_group_id' => 1,
            'sort_index' => 1,
            'description' => $this->faker->text(20),
            'is_required' => true,
            'type' => 'options',
            'options' => "First Option\nSecond Option\n",
            'code' => 'good'
        ];
    }
}
