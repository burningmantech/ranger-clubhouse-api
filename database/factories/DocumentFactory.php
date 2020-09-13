<?php

namespace Database\Factories;

use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DocumentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Document::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'tag' => $this->faker->uuid,
            'description'=> $this->faker->text(20),
            'body' => $this->faker->text(100),
            'person_create_id' => 1,
            'person_update_id' => 2,
            'created_at' => '2020-01-02 12:34:56',
            'updated_at' => '2020-01-02 12:34:56',
        ];
    }
}
