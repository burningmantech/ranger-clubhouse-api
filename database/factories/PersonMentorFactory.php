<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

use App\Models\PersonMentor;


class PersonMentorFactory extends Factory
{
    protected $model = PersonMentor::class;

    public function definition()
    {
return [
        'status' => 'pending',
    ];
}
}
