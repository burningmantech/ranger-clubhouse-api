<?php

namespace Database\Factories;

use App\Models\PersonEvent;
use Illuminate\Database\Eloquent\Factories\Factory;


class PersonEventFactory extends Factory
{
    protected $model = PersonEvent::class;

    public function definition()
    {
        return [
            'year' => current_year(),
        ];
    }
}
