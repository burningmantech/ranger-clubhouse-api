<?php

namespace Database\Factories;

use App\Models\PersonLanguage;
use Illuminate\Database\Eloquent\Factories\Factory;

class PersonLanguageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'proficiency' => PersonLanguage::PROFICIENCY_UNKNOWN
        ];
    }
}
