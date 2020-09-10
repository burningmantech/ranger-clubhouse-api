<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

use App\Models\PersonMessage;


class PersonMessageFactory extends Factory
{
    protected $model = PersonMessage::class;

    public function definition()
    {
return [
        'subject'           => $this->faker->text($this->faker->numberBetween(10,15)),
        'message_from'      => $this->faker->firstName,
        'body'              => $this->faker->text($this->faker->numberBetween(10,15)),
        'creator_person_id' => 1,
    ];
}
}
