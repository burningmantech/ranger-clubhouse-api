<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

use App\Models\Bmid;


class BmidFactory extends Factory
{
    protected $model = Bmid::class;

    public function definition()
    {
return [
        'status'    => 'in_prep',
        'showers'   => false,
        'meals'     => null,
    ];
}
}
