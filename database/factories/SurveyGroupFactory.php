<?php

namespace Database\Factories;

use App\Models\SurveyGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

class SurveyGroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'survey_id' => 1,
            'sort_index' => 1,
            'type' => SurveyGroup::TYPE_NORMAL,
            'title' => $this->faker->text(20),
            'description' => $this->faker->text(20),
            'report_title' => '',
        ];
    }
}
