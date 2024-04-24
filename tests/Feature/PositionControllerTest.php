<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\PersonEvent;
use App\Models\PersonPosition;
use App\Models\PersonSlot;
use App\Models\Position;
use App\Models\Role;
use App\Models\Slot;
use App\Models\Timesheet;
use App\Models\TraineeStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PositionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        $this->signInUser();
    }

    /*
     * Get the positions
     */

    public function testIndexPosition()
    {
        $position = Position::factory()->create([
            'min' => 1,
            'max' => 10,
            'count_hours' => true,
            'type' => 'Frontline',
            'short_title' => 'XYZZY'
        ]);

        $response = $this->json('GET', 'position');
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'position.*.id');

        $response->assertJson([
            'position' => [
                [
                    'id' => $position->id,
                    'title' => $position->title,
                    'min' => 1,
                    'max' => 10,
                    'count_hours' => true,
                    'type' => 'Frontline',
                    'short_title' => 'XYZZY'
                ]
            ]
        ]);
    }

    /*
     * Create a position
     */

    public function testCreatePosition()
    {
        $this->addRole(Role::TECH_NINJA);
        $data = [
            'title' => 'Gerlach Pizza Patrol',
            'short_title' => 'PIZZA',
            'min' => 5,
            'max' => 25,
            'count_hours' => true,
            'type' => 'Frontline'
        ];

        $response = $this->json('POST', 'position', ['position' => $data]);
        $response->assertStatus(200);
        $this->assertDatabaseHas('position', $data);
    }

    /*
     * Update a position
     */

    public function testUpdatePosition()
    {
        $this->addRole(Role::ADMIN);
        $position = Position::factory()->create();

        $response = $this->json('PATCH', "position/{$position->id}", [
            'position' => ['title' => 'Something Title', 'active' => false]
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('position',
            ['id' => $position->id, 'title' => 'Something Title', 'active' => false]);
    }

    /**
     * Try to successfully update the position roles
     */

    public function testUpdatePositionRoles()
    {
        $this->addRole(Role::TECH_NINJA);
        $position = Position::factory()->create();

        $response = $this->json('PATCH', "position/{$position->id}", [
            'position' => ['role_ids' => [Role::MANAGE]]
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('position_role', [ 'position_id' => $position->id, 'role_id' => Role::MANAGE ]);
    }

    /**
     * Try to update the position roles without the right permissions
     */

    public function testUpdatePositionRolesForNonTechNinja()
    {
        $this->addRole(Role::ADMIN);
        $position = Position::factory()->create();

        $response = $this->json('PATCH', "position/{$position->id}", [
            'position' => ['role_ids' => [Role::ADMIN]]
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('position_role', [ 'position_id' => $position->id, 'role_id' => Role::ADMIN ]);
    }


    /*
     * Delete a position
     */

    public function testDeletePosition()
    {
        $this->addRole(Role::TECH_NINJA);
        $position = Position::factory()->create();
        $positionId = $position->id;

        $response = $this->json('DELETE', "position/{$positionId}");
        $response->assertStatus(204);
        $this->assertDatabaseMissing('position', ['id' => $positionId]);
    }

    /*
     * Test the Sandman Qualification Report
     */

    public function testSandmanQualificationReport()
    {
        $this->addRole(Role::MANAGE);

        $sandmanTraining = Slot::factory()->create([
            'description' => 'Stop that runner',
            'position_id' => Position::SANDMAN_TRAINING,
            'begins' => date('Y-01-01 00:00:00'),
            'ends' => date('Y-01-01 00:10:00')
        ]);

        $sandmanShift = Slot::factory()->create([
            'description' => 'Burn All The Things',
            'position_id' => Position::SANDMAN,
            'begins' => date('Y-12-31 00:00:00'),
            'ends' => date('Y-12-31 00:10:00')
        ]);

        // Create a person who is fully qualified
        // trained, has Sandman position, worked BP, signed affidavit, signed up to work this year.
        //
        $personQualified = Person::factory()->create([
            'callsign' => 'A Qualified',
        ]);

        PersonEvent::factory()->create([
            'person_id' => $personQualified->id,
            'year' => current_year(),
            'sandman_affidavit' => true
        ]);

        PersonPosition::factory()->create([
            'person_id' => $personQualified->id,
            'position_id' => Position::SANDMAN,
        ]);

        Timesheet::factory()->create([
            'person_id' => $personQualified->id,
            'position_id' => Position::BURN_PERIMETER,
            'on_duty' => date('Y-08-20 23:00:00'),
            'off_duty' => date('Y-08-20 23:30:00')
        ]);

        TraineeStatus::factory()->create([
            'slot_id' => $sandmanTraining->id,
            'person_id' => $personQualified->id,
            'passed' => true
        ]);

        PersonSlot::factory()->create([
            'person_id' => $personQualified->id,
            'slot_id' => $sandmanShift->id
        ]);

        // .. and a person who is not at all qualified
        $personUnqualified = Person::factory()->create(['callsign' => 'B Unqualified']);
        PersonPosition::factory()->create([
            'person_id' => $personUnqualified->id,
            'position_id' => Position::SANDMAN
        ]);

        $response = $this->json('GET', 'position/sandman-qualified', ['year' => date('Y')]);
        $response->assertStatus(200);
        $response->assertJsonCount(2, 'sandpeople.*.id');

        $response->assertJson([
            'sandpeople' => [
                [
                    'id' => $personQualified->id,
                    'callsign' => $personQualified->callsign,
                    'sandman_affidavit' => 1,
                    'has_experience' => 1,
                    'is_trained' => 1,
                    'is_signed_up' => 1,
                ],
                [
                    'id' => $personUnqualified->id,
                    'callsign' => $personUnqualified->callsign,
                    'sandman_affidavit' => 0,
                    'has_experience' => 0,
                    'is_trained' => 0,
                    'is_signed_up' => 0,
                ]

            ]
        ]);
    }


    public function createBulkPeople()
    {
        Position::factory()->create(['id' => Position::HQ_WINDOW, 'title' => 'HQ Window']);
        Position::factory()->create(['id' => Position::DIRT_GREEN_DOT, 'title' => 'Dirt Green Dot']);

        $person1 = Person::factory()->create();
        PersonPosition::factory()->create([
            'person_id' => $person1->id,
            'position_id' => Position::HQ_WINDOW,
        ]);

        $this->person1 = $person1;
    }

    /*
     * Verify a bulk position grant/revoke
     */

    public function testVerifyBulkPositionGrantRevoke()
    {
        $this->addRole(Role::ADMIN);
        $this->createBulkPeople();
        $person1 = $this->person1;

        $response = $this->json('POST', 'position/' . Position::DIRT_GREEN_DOT . '/bulk-grant-revoke', [
            'callsigns' => $person1->callsign,
            'grant' => 1,
            'commit' => 0
        ]);

        $response->assertStatus(200);

        $response->assertJson([
            'people' => [
                [
                    'id' => $person1->id,
                    'callsign' => $person1->callsign,
                    'success' => true,
                ],
            ]
        ]);
    }

    /*
     * Commit a bulk position grant
     */

    public function testCommitBulkPositionGrant()
    {
        $this->addRole(Role::ADMIN);

        $this->createBulkPeople();
        $person1 = $this->person1;

        $response = $this->json('POST', 'position/' . Position::DIRT_GREEN_DOT . '/bulk-grant-revoke', [
            'callsigns' => $person1->callsign,
            'grant' => 1,
            'commit' => 1
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'people' => [
                [
                    'id' => $person1->id,
                    'callsign' => $person1->callsign,
                    'success' => true,
                ]
            ]
        ]);

        $this->assertDatabaseHas('person_position', [
            'person_id' => $person1->id,
            'position_id' => Position::DIRT_GREEN_DOT,
        ]);
    }

    /*
     * Fail a bulk position grant with an unknown callsign
     */

    public function testFailBulkPositionGrant()
    {
        $this->addRole(Role::ADMIN);
        $this->createBulkPeople();

        $response = $this->json('POST', 'position/' . Position::HQ_WINDOW . '/bulk-grant-revoke', [
            'callsigns' => 'nosuchcallsign',
            'grant' => 1,
            'commit' => 0
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'people' => [
                [
                    'callsign' => 'nosuchcallsign',
                    'errors' => 'Callsign not found',
                ],
            ]
        ]);
    }

    /*
     * Fail a bulk position grant with a position already granted.
     */

    public function testFailBulkPositionAlreadyGranted()
    {
        $this->addRole(Role::ADMIN);
        $this->createBulkPeople();
        $person1 = $this->person1;

        $response = $this->json('POST', 'position/' . Position::HQ_WINDOW . '/bulk-grant-revoke', [
            'callsigns' => $person1->callsign,
            'grant' => 1,
            'commit' => 0
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'people' => [
                [
                    'callsign' => $person1->callsign,
                    'errors' => 'Position already granted',
                ],
            ]
        ]);
    }
}
