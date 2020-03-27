<?php

use App\Models\Person;
use App\Models\PersonPosition;
use App\Models\PersonStatus;
use App\Models\Role;

use Illuminate\Database\Seeder;

class AdminPersonTableSeeder extends Seeder
{
    /**
     * Add an admin user to the database
     *
     * @return void
     */
    public function run()
    {
        DB::table('person')->delete();

        $person = Person::create([
            'email' => 'admin@example.com',
            'callsign' => 'Admin',
            'first_name' => 'Admin',
            'last_name' => 'Account',
            'street1' => '660 Alabama Street',
            'city' => 'San Francisco',
            'zip' => '94110',
            'state' => 'CA',
            'country' => 'US',
            'status' => 'active',
            'home_phone' => '555-555-5555'
        ]);

        PersonStatus::record($person->id, '', Person::ACTIVE, 'database seed', $person->id);

        $person->changePassword('forkyourburn');

        // Setup the default roles & positions
        foreach (Role::all()  as $role) {
            DB::table('person_role')->insert([
                'person_id' => $person->id,
                'role_id' => $role->id
            ]);
        }
        PersonPosition::resetPositions($person->id, 'database seed', Person::ADD_NEW_USER);
    }
}
