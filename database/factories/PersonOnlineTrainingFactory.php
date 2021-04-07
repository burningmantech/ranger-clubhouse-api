<?php

namespace Database\Factories;

use App\Models\PersonOnlineTraining;
use Illuminate\Database\Eloquent\Factories\Factory;

class PersonOnlineTrainingFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = PersonOnlineTraining::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'type' => PersonOnlineTraining::MOODLE,
            'completed_at' => now()
        ];
    }
}
