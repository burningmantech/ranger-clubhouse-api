<?php

namespace Database\Factories;

use App\Models\HandleReservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\HandleReservation>
 */
class HandleReservationFactory extends Factory
{
    protected $model = HandleReservation::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'handle' => $this->faker->unique()->word(),
        ];
    }
}
