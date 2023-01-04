<?php

namespace Database\Factories;

use App\Models\Slot;
use Illuminate\Database\Eloquent\Factories\Factory;


class SlotFactory extends Factory
{
    protected $model = Slot::class;

    public function definition(): array
    {
        return [
            'active' => true,
            'min' => 1,
            'max' => 10,
            'description' => 'slot',
            'signed_up' => 0,
            'timezone' => 'America/Los_Angeles',
        ];
    }
}
