<?php

namespace Database\Factories;

use App\Models\Provision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Provision>
 */
class ProvisionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */

    public function definition(): array
    {
        return [
            'type' => Provision::EVENT_RADIO,
            'status' => Provision::AVAILABLE,
            'item_count' => 0,
            'expires_on' => date('Y-12-31'),
        ];
    }
}
