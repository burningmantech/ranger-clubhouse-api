<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        return [
            'tag' => $this->faker->uuid(),
            'description' => $this->faker->text(20),
            'body' => $this->faker->text(100),
            'person_create_id' => 1,
            'person_update_id' => 2,
            'created_at' => '2020-01-02 12:34:56',
            'updated_at' => '2020-01-02 12:34:56',
        ];
    }
}
