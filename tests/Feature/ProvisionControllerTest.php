<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\Provision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ProvisionControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    /*
     * have each test have a fresh user that is logged in.
     */

    public function setUp(): void
    {
        parent::setUp();
        $this->signInUser();
    }

    private function createProvision()
    {
        return Provision::factory()->create([
            'type' => Provision::ALL_EAT_PASS,
            'status' => Provision::AVAILABLE,
            'person_id' => $this->user->id,
            'source_year' => (int)date('Y'),
            'expires_on' => date('Y-12-31'),
        ]);
    }

    /*
     * Test showing an provision
     */

    public function testShowProvisionSuccess()
    {
        $provision = $this->createProvision();

        $response = $this->json('GET', "provision/{$provision->id}");
        $response->assertStatus(200);
        $response->assertJson([
            'provision' => [
                'id' => $provision->id,
                'type' => $provision->type,
                'status' => $provision->status,
                'source_year' => $provision->source_year,
                'expires_on' => date('Y-12-31')
            ]
        ]);
    }

    /*
     * Test not finding an provision
     */

    public function testShowNonExistentProvisionFailure()
    {
        $response = $this->json('GET', "provision/99999999");
        $response->assertStatus(404);
    }

    /*
     * Test creating a provision
     */

    public function testCreateProvisionSuccess()
    {
        $this->addAdminRole();

        $data = [
            'person_id' => $this->user->id,
            'type' => Provision::ALL_EAT_PASS,
            'status' => Provision::AVAILABLE,
            'source_year' => date('Y'),
            'expires_on' => date('Y-12-31'),
        ];

        $response = $this->json('POST', 'provision', ['provision' => $data]);
        $response->assertStatus(200);
        $this->assertDatabaseHas('provision', $data);
    }

    /*
     * Update an access Document for allowed user
     */

    public function testProvisionUpdateSuccess()
    {
        $provision = $this->createProvision();

        $response = $this->json('PUT', "provision/{$provision->id}", ['provision' => [
            'status' => Provision::BANKED
        ]]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('provision', [
            'id' => $provision->id,
            'status' => Provision::BANKED
        ]);
    }


    /*
     * Delete an provision
     */

    public function testProvisionDeleteSuccess()
    {
        $this->addAdminRole();
        $provision = $this->createProvision();

        $response = $this->json('DELETE', "provision/{$provision->id}");
        $response->assertStatus(204);
        $this->assertDatabaseMissing('provision', ['id' => $provision->id]);
    }

    /*
     * Test changing the status on an provision
     */

    public function testStatusChangeSuccess()
    {
        $provision = $this->createProvision();

        $response = $this->json('PATCH', "provision/statuses",
            [
                'statuses' => [
                    [
                        'id' => $provision->id,
                        'status' => Provision::BANKED
                    ]]

            ]);
        $response->assertStatus(200);
        $this->assertDatabaseHas('provision', ['id' => $provision->id, 'status' => Provision::BANKED]);
    }

    /*
  * Test Clean Provisions from prior event. Mark non-bankable unclaimed docs as expired,
  * and submitted documents as used.
  */

    public function testCleanProvisionsFromPriorEvent()
    {
        $this->addAdminRole();

        $year = date('Y');

        $person = Person::factory()->create();

        $submitted = Provision::factory()->create([
            'person_id' => $person->id,
            'source_year' => $year,
            'type' => Provision::ALL_EAT_PASS,
            'status' => Provision::SUBMITTED,
        ]);

        $banked = Provision::factory()->create([
            'person_id' => $person->id,
            'source_year' => $year,
            'type' => Provision::WET_SPOT,
            'status' => Provision::BANKED,
        ]);

        $response = $this->json('POST', 'provision/clean-provisions');
        $response->assertStatus(200);

        $response->assertJsonCount(1, 'provisions.*.id');
        $response->assertJson(['provisions' => [
            [
                'id' => $submitted->id,
                'status' => Provision::USED
            ]
        ]]);

        $this->assertDatabaseHas('provision', ['id' => $submitted->id, 'status' => Provision::USED]);
        $this->assertDatabaseHas('provision', ['id' => $banked->id, 'status' => Provision::BANKED]);

        $qualified = Provision::factory()->create([
            'person_id' => $person->id,
            'source_year' => $year,
            'type' => Provision::EVENT_RADIO,
            'status' => Provision::AVAILABLE,
            'is_allocated' => true,
        ]);
        $response = $this->json('POST', 'provision/clean-provisions');
        $response->assertStatus(200);

        $response->assertJsonCount(1, 'provisions.*.id');
        $response->assertJson(['provisions' => [
            [
                'id' => $qualified->id,
                'status' => Provision::EXPIRED
            ]
        ]]);
        $this->assertDatabaseHas('provision', ['id' => $qualified->id, 'status' => Provision::EXPIRED]);
    }

    /*
     * Test banking provisions.
     */

    public function testBankProvisions()
    {
        $this->addAdminRole();

        $year = date('Y');

        $person = Person::factory()->create();
        $qualified = Provision::factory()->create([
            'person_id' => $person->id,
            'source_year' => $year,
            'type' => Provision::ALL_EAT_PASS,
            'status' => Provision::AVAILABLE,
        ]);

        // Should not bank this.
        $available = Provision::factory()->create([
            'person_id' => $person->id,
            'source_year' => $year,
            'type' => Provision::EVENT_EAT_PASS,
            'status' => Provision::CLAIMED,
        ]);

        $response = $this->json('POST', 'provision/bank-provisions');

        $response->assertStatus(200);
        $response->assertJson([
            'provisions' => [
                ['id' => $qualified->id, 'status' => Provision::BANKED]
            ]
        ]);

        $response->assertJsonCount(1, 'provisions.*.id');
        $this->assertDatabaseHas('provision', ['id' => $qualified->id, 'status' => Provision::BANKED]);
        $this->assertDatabaseHas('provision', ['id' => $available->id, 'status' => Provision::CLAIMED]);
    }

    /*
     * Test expiring provisions
     */

    public function testExpireProvisions()
    {
        $this->addAdminRole();

        $year = (int)date('Y');
        $lastYear = $year - 1;
        $nextYear = $year + 1;

        $person = Person::factory()->create();
        $expire = Provision::factory()->create([
            'person_id' => $person->id,
            'source_year' => $year,
            'type' => Provision::ALL_EAT_PASS,
            'status' => Provision::AVAILABLE,
            'expires_on' => "$lastYear-08-20"
        ]);

        // Should not bank this.
        $ignore = Provision::factory()->create([
            'person_id' => $person->id,
            'source_year' => $year,
            'type' => Provision::ALL_EAT_PASS,
            'status' => Provision::AVAILABLE,
            'expires_on' => "$nextYear-08-20"
        ]);

        $response = $this->json('POST', 'provision/expire-provisions');
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'provisions.*.id');
        $response->assertJson([
            'provisions' => [
                [
                    'id' => $expire->id,
                    'status' => Provision::EXPIRED,
                    'person' => [
                        'id' => $person->id,
                        'callsign' => $person->callsign
                    ]
                ]
            ]
        ]);

        $this->assertDatabaseHas('provision', ['id' => $expire->id, 'status' => Provision::EXPIRED]);
        $this->assertDatabaseHas('provision', ['id' => $ignore->id, 'status' => Provision::AVAILABLE]);
    }

}
