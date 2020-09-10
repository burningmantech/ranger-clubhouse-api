<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

use App\Models\Position;


class PositionFactory extends Factory
{
    protected $model = Position::class;

    public function definition()
    {
return [
        'title' => $this->faker->text(10),
        'max'   => 1,
        'min'   => 0
    ];
}
}
