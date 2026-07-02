<?php

namespace Tests\Feature;

use App\Lib\UserAuthentication;
use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAuthenticationDevLoginTest extends TestCase
{
    use RefreshDatabase;

    public function testUnknownCallsign()
    {
        $response = UserAuthentication::attemptDevLogin('no-such-callsign');
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals(['error' => 'invalid-callsign'], $response->getData(true));
    }

    public function testSuccess()
    {
        $person = Person::factory()->create(['callsign' => 'Dev Tester']);
        $response = UserAuthentication::attemptDevLogin('Dev Tester');
        $this->assertEquals(200, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertArrayHasKey('access_token', $data);
        $this->assertArrayHasKey('token_type', $data);
        $this->assertArrayHasKey('expires_in', $data);
        $this->assertEquals($person->id, $data['person_id']);
    }

    public function testSuspendedPerson()
    {
        Person::factory()->create(['callsign' => 'Suspended Sam', 'status' => 'suspended']);
        $response = UserAuthentication::attemptDevLogin('Suspended Sam');
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals(['error' => 'account-suspended'], $response->getData(true));
    }
}
