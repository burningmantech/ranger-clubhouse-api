<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

use App\Models\PositionCredit;


class PositionCreditFactory extends Factory
{
    protected $model = PositionCredit::class;

    public function definition()
    {
return [
        'position_id'   => 1,
        'credits_per_hour'  => 1.00,
        'description'   => $this->faker->text(20),
    ];
}
}
