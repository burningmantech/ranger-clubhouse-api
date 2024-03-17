<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class VehicleFactory extends Factory
{
    public function definition(): array
    {
        $year = date('Y');
        return [
            'person_id' => 99999,
            'license_number' => $this->faker->text(10),
            'license_state' => 'CA',
            'event_year' => $year,
            'type' => 'personal',
            'vehicle_year' => $year,
            'vehicle_make' => $this->faker->text(10),
            'vehicle_model' => $this->faker->text(10),
            'vehicle_color' => $this->faker->text(10)
        ];
    }
}
