<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Models\SurveyGroup;
use Faker\Generator as Faker;

$factory->define(SurveyGroup::class, function (Faker $faker) {
    return [
        'survey_id' => 1,
        'sort_index' => 1,
        'title' => $faker->text(20),
        'description' => $faker->text(20),
        'is_trainer_group' => false,
    ];
});
