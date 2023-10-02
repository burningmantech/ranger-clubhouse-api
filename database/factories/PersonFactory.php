<?php

namespace Database\Factories;

use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PersonFactory extends Factory
{
    protected $model = Person::class;

    public function definition()
    {
        $salt = "0123467890123456789";
        $uuid = (string)Str::uuid();
        return [
            'alt_phone' => '',
            'behavioral_agreement' => true,
            'bpguid' => $uuid,
            'callsign' => join("", $this->faker->unique()->words(2)),
            'callsign_approved' => true,
            'city' => 'Smallville',
            'country' => 'USA',
            'created_at' => '2019-01-01 00:00:00',
            'email' => $uuid . '@example.com',
            'first_name' => 'Bravo',
            'home_phone' => '415-555-1212',
            'last_name' => 'Delta',
            'on_site' => false,
            'password' => $salt . ":" . sha1($salt . "ineedashower!"),
            'state' => 'CA',
            'status' => 'active',
            'street1' => '1 Main Street',
            'zip' => '94501',
        ];
    }
}
