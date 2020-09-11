<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

use App\Models\TraineeStatus;


class TraineeStatusFactory extends Factory
{
    protected $model = TraineeStatus::class;

    public function definition()
    {
return [
        'passed'   => false,
    ];
}
}
