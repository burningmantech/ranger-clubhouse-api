<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

use App\Models\Role;


class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition()
    {
return [
        'title'  => substr($this->faker->name, 0, 10),
    ];
}
}
