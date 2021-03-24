<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\SurveyGroup;

class SurveyGroupFactory extends Factory
{
    protected $model = SurveyGroup::class;

    public function definition()
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
