<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class HelpFactory extends Factory
{
    public function definition(): array
    {
        return [
            'slug' => Str::random(10),
            'title' => $this->faker->uuid(),
            'body' => $this->faker->text(10),
            'tags' => '',
        ];
    }
}
