<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

use App\Models\TimesheetMissing;


class TimesheetMissingFactory extends Factory
{
    protected $model = TimesheetMissing::class;

    public function definition()
    {
return [ 'partner' => '' ];
}
}
