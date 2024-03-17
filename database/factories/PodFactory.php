<?php

namespace Database\Factories;

use App\Models\Pod;
use Illuminate\Database\Eloquent\Factories\Factory;

class PodFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => Pod::TYPE_SHIFT,
            'sort_index' => 1,
            'slot_id' => null,
            'person_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}