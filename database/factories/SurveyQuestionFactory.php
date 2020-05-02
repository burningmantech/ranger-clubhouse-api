<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Models\SurveyQuestion;
use Faker\Generator as Faker;

$factory->define(SurveyQuestion::class, function (Faker $faker) {
    return [
        'survey_id' => 1,
        'survey_group_id' => 1,
        'sort_index' => 1,
        'description' => $faker->text(20),
        'is_required' => true,
        'type' => 'options',
        'options' => "First Option\nSecond Option\n",
        'code' => 'good'
    ];
});
