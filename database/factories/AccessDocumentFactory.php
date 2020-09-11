<?php

namespace Database\Factories;

use App\Models\AccessDocument;
use Illuminate\Database\Eloquent\Factories\Factory;


class AccessDocumentFactory extends Factory
{
    protected $model = AccessDocument::class;

    public function definition()
    {
        return [
            'create_date' => date('Y-m-d H:i:s'),
        ];
    }
}
