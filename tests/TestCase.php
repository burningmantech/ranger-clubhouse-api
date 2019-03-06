<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

use Mockery;
use App\Models\Person;
use App\Models\Role;
use App\Models\PersonRole;
use App\Models\PersonPosition;
use App\Models\Setting;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    public $user;

    public function setUp() : void
    {
        parent::setUp();
        // force garbage collection before each test
        // Faker triggers a memory allocation bug.
        gc_collect_cycles();
    }

    public function createUser()
    {
        $this->user = factory(Person::class)->create();
        $this->addRole(Role::LOGIN);
    }


    public function signInUser()
    {
        $this->createUser();
        $this->actingAs($this->user);
    }


    public function addRole($roles, $user = null)
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
    }


    public function addPosition($positions, $user = null)
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
    }

    public function addAdminRole($user = null)
    {
        $this->addRole(Role::ADMIN, $user);
    }

    public function setting($name, $value)
    {
        if (is_numeric($value)) {
            $type = 'integer';
        } else if (is_bool($value)) {
            $type = 'bool';
        } else {
            $type = 'string';
        }

        Setting::where('name', $name)->delete();
        Setting::insert([
            'name'  => $name,
            'type'  => $type,
            'value' => $value,
        ]);
    }
}
