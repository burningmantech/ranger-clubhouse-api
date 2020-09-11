<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

use App\Models\TrainerStatus;


class TrainerStatusFactory extends Factory
{
    protected $model = TrainerStatus::class;

    public function definition()
    {
return [
        'status'   => TrainerStatus::ATTENDED
    ];
}
}
