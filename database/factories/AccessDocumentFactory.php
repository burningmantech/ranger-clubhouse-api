<?php

namespace Database\Factories;

use App\Models\AccessDocument;
use Illuminate\Database\Eloquent\Factories\Factory;


class AccessDocumentFactory extends Factory
{
    protected $model = AccessDocument::class;

    public function definition(): array
    {
        return [
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
