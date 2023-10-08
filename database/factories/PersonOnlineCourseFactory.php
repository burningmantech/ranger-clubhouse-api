<?php

namespace Database\Factories;

use App\Models\PersonOnlineCourse;
use Illuminate\Database\Eloquent\Factories\Factory;

class PersonOnlineCourseFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = PersonOnlineCourse::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        return [
            'type' => PersonOnlineCourse::TYPE_MOODLE,
            'completed_at' => now()
        ];
    }
}
