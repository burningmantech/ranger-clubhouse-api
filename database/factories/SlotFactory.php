<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

use App\Models\Slot;


class SlotFactory extends Factory
{
    protected $model = Slot::class;

    public function definition()
    {
return [
        'active'    => true,
        'min'       => 1,
        'max'       => 10,
        'description'   => 'slot',
        'signed_up' => 0,
    ];
}
}
