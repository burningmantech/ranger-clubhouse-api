<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

use App\Models\Person;
use App\Models\Role;
use App\Models\PersonRole;


abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    public $user;

    public function createUser() {
        $this->user = factory(Person::class)->create();
        $this->addRole(Role::LOGIN);
    }

    public function signInUser() {
        $this->createUser();
        $this->actingAs($this->user);
    }

    public function addRole($roles)
    {
        if (!is_array($roles)) {
            $roles = [ $roles ];
        }

        $rows = [];
        foreach ($roles as $role) {
            $rows[] = [ 'person_id' => $this->user->id, 'role_id' => $role ];
        }

        PersonRole::insert($rows);
    }
}
