<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

use App\Models\Help;
use Carbon\Carbon;
use Illuminate\Support\Str;


class HelpFactory extends Factory
{
    protected $model = Help::class;

    public function definition()
    {
return [
        'slug'  => Str::random(10),
        'title' => $this->faker->uuid,
        'body'  => $this->faker->text(10),
        'tags'  => '',
    ];
}
}
