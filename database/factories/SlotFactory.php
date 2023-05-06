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
            'timezone_abbr' => 'PST',
        ];
    }

    /**
     * Configure the model factory.
     */

    public function configure(): static
    {
        return $this->afterMaking(function ($slot) {
            $slot->begins_year = $slot->begins->year;
            $slot->begins_time = $slot->begins_adjusted->timestamp;
            $slot->ends_time = $slot->ends_adjusted->timestamp;
            $slot->duration = $slot->ends_time - $slot->begins_time;
        });
    }
}
