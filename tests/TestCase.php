<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

use Mockery;
use App\Models\Person;
use App\Models\Role;
use App\Models\PersonRole;
use App\Models\PersonPosition;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    public $user;


    public function setUp() {
        parent::setUp();
        // force garbage collection before each test
        // Faker triggers a memory allocation bug.
        gc_collect_cycles();
    }

    public function createUser()
    {
        $this->user = factory(Person::class)->create();
        $this->addRole(Role::LOGIN);

    }//end createUser()


    public function signInUser()
    {
        $this->createUser();
        $this->actingAs($this->user);

    }//end signInUser()


    public function addRole($roles, $user=null)
    {
        if (!$user) {
            $user = $this->user;
        }

        if (!is_array($roles)) {
            $roles = [ $roles ];
        }

        $rows = [];
        foreach ($roles as $role) {
            $rows[] = [
                'person_id' => $user->id,
                'role_id'   => $role,
            ];
        }

        PersonRole::insert($rows);

    }//end addRole()


    public function addPosition($positions, $user=null)
    {
        if (!$user) {
            $user = $this->user;
        }

        if (!is_array($positions)) {
            $positions = [ $positions ];
        }

        foreach ($positions as $p) {
            factory(PersonPosition::class)->create(
                [
                    'person_id'   => $user->id,
                    'position_id' => $p,
                ]
            );
        }

    }//end addPosition()

/*
    public function mock($class)
    {
        $mock = \Mockery::mock($class);
        App::instance($class, $mock);

        return $mock;

    }//end mock()
*/

}//end class
