<?php

namespace Database\Factories;

use App\Models\TrainerStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class TrainerStatusFactory extends Factory
{

    public function definition(): array
    {
        return [
            'status' => TrainerStatus::ATTENDED
        ];
    }
}
