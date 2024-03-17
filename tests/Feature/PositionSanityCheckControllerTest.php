<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\PersonMentor;
use App\Models\PersonPosition;
use App\Models\PersonTeam;
use App\Models\Position;
use App\Models\Role;
use App\Models\Team;
use App\Models\Timesheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PositionSanityCheckControllerTest extends TestCase
{
    use RefreshDatabase;

    public ?Person $person;
    public ?Person $trainer;
    public ?Person $shinyPenny;
    public ?int $personYear;

    public function setUp(): void
    {
        parent::setUp();
        $this->signInUser();

        $person = $this->person = Person::factory()->create(['callsign' => 'A Callsign']);
        $personYear = $this->personYear = (int)date('Y') - 2;

        // person is green dot and doesn't have the other GD positions
        PersonPosition::factory()->create([
            'person_id' => $person->id,
            'position_id' => Position::DIRT_GREEN_DOT
        ]);

        // person isn't a shiny penny and has the position
        PersonPosition::factory()->create([
            'person_id' => $person->id,
            'position_id' => Position::DIRT_SHINY_PENNY
        ]);

        PersonMentor::factory()->create([
            'person_id' => $person->id,
            'mentor_id' => $this->user->id,
            'mentor_year' => $personYear,
            'status' => 'pass'
        ]);

        // person has a Login Management position but no LM role
        PersonPosition::factory()->create([
            'person_id' => $person->id,
            'position_id' => Position::OPERATOR
        ]);

        Position::factory()->create([
            'id' => Position::OPERATOR,
            'title' => 'Operator'
        ]);

        Position::factory()->create([
            'id' => Position::TRAINER,
            'title' => 'Trainer'
        ]);

        // Shiny Penny without the Dirt Shiny Position
        $this->shinyPenny = Person::factory()->create(['callsign' => 'B Callsign']);
        PersonMentor::factory()->create([
            'person_id' => $this->shinyPenny->id,
            'mentor_id' => $this->user->id,
            'mentor_year' => date('Y'),
            'status' => 'pass'
        ]);

        Timesheet::factory()->create([
            'person_id' => $this->shinyPenny->id,
            'position_id' => Position::ALPHA,
            'on_duty' => date('Y-01-01 00:00'),
            'off_duty' => date('Y-01-01 01:00'),
        ]);

        // Trainer to test Login Management Year Round
        $this->trainer = Person::factory()->create(['callsign' => 'Trainer']);
        // person has a Login Management position but no LM role
        PersonPosition::factory()->create([
            'person_id' => $this->trainer->id,
            'position_id' => Position::TRAINER
        ]);

    }

    /*
     * Test the Position Sanity Checker (Part 1 - inspection)
     */

    public function testPositionSanityCheckerInspection()
    {
        $this->addRole(Role::MANAGE);

        $person = $this->person;
        $personYear = $this->personYear;
        $shinyPenny = $this->shinyPenny;

        $response = $this->json('GET', 'position/sanity-checker');
        $response->assertStatus(200);

        $response->assertJsonCount(2, 'shiny_pennies.*.id');

        $response->assertJson([
            // Response is sorted by year descending, callsign
            'shiny_pennies' => [
                [
                    'id' => $person->id,
                    'callsign' => $person->callsign,
                    'has_shiny_penny' => 1,
                    'year' => $personYear,
                ],
                [
                    'id' => $shinyPenny->id,
                    'callsign' => $shinyPenny->callsign,
                    'has_shiny_penny' => 0,
                    'year' => (int)date('Y'),
                ],
            ]
        ]);
    }

    public function testDeactivatedPositionsInspection()
    {
        $person = $this->person;

        Position::factory()->create([
            'id' => Position::HQ_LEAD,
            'title' => 'HQ Lead',
            'active' => 0
        ]);

        Position::factory()->create([
            'id' => Position::HQ_RUNNER,
            'title' => 'HQ Runner',
            'active' => 0
        ]);

        PersonPosition::factory()->create([
            'person_id' => $person->id,
            'position_id' => Position::HQ_LEAD
        ]);

        PersonPosition::factory()->create([
            'person_id' => $person->id,
            'position_id' => Position::HQ_RUNNER
        ]);

        $this->addRole(Role::MANAGE);

        $person = $this->person;

        $response = $this->json('GET', 'position/sanity-checker');
        $response->assertStatus(200);
        $response->assertJsonCount(2, 'deactivated_positions.*');
        $response->assertJson([
            'deactivated_positions' => [
                [
                    "id" => Position::HQ_LEAD,
                    "title" => "HQ Lead",
                    "people" => [
                        [
                            "id" => $person->id,
                            "callsign" => "A Callsign",
                            "status" => "active"
                        ]
                    ]
                ],
                [
                    "id" => Position::HQ_RUNNER,
                    "title" => "HQ Runner",
                    "people" => [
                        [
                            "id" => $person->id,
                            "callsign" => "A Callsign",
                            "status" => "active"
                        ]
                    ]
                ]
            ]
        ]);
    }

    /*
     * Test Position Sanity Checker Repair
     */

    public function testPositionSanityCheckerRepair()
    {
        $this->addRole(Role::ADMIN);

        $person = $this->person;
        $shinyPenny = $this->shinyPenny;

        $team = Team::factory()->create([
            'title' => 'Team #1',
        ]);

        $teamPosition = Position::factory()->create([
            'title' => 'Team #1 Position',
            'team_id' => $team->id,
            'active' => true,
            'team_category' => Position::TEAM_CATEGORY_ALL_MEMBERS,
        ]);

        PersonTeam::factory()->create([
            'person_id' => $person->id,
            'team_id' => $team->id,
        ]);

        $response = $this->json('POST', 'position/repair', [
            'repair' => 'team_positions',
            'people_ids' => [$person->id],
            'repair_params' => [
                $person->id => [$teamPosition->id]
            ]
        ]);

        $response->assertStatus(200);
        $response->assertJsonCount(1, '*.id');
        $response->assertJson([
            [
                'id' => $person->id,
                'position_id' => $teamPosition->id,
                'messages' => ['added position']
            ]
        ]);

        $this->assertDatabaseHas('person_position', [
            'person_id' => $person->id,
            'position_id' => $teamPosition->id
        ]);

        $response = $this->json('POST', 'position/repair', ['repair' => 'shiny_pennies', 'people_ids' => [$person->id, $shinyPenny->id]]);
        $response->assertStatus(200);
        $response->assertJsonCount(2, '*.id');
        $response->assertJson([
            [
                'id' => $person->id,
                'messages' => ['not a Shiny Penny, position removed']
            ],
            [
                'id' => $shinyPenny->id,
                'messages' => ['is a Shiny Penny, position added']
            ]
        ]);

        $this->assertDatabaseMissing('person_position', [
            'person_id' => $person->id,
            'position_id' => Position::DIRT_SHINY_PENNY
        ]);

        $this->assertDatabaseHas('person_position', [
            'person_id' => $shinyPenny->id,
            'position_id' => Position::DIRT_SHINY_PENNY
        ]);
    }

    public function testDeactivatedRepairInvalidPosition()
    {
      //  $this->withoutExceptionHandling();
        $this->addRole(Role::ADMIN);
        $person = $this->person;

        Position::factory()->create([
            'id' => Position::HQ_RUNNER,
            'title' => 'HQ Runner',
            'active' => 1
        ]);

        $response = $this->json('POST', 'position/repair', [
            'repair' => 'deactivated_positions',
            'people_ids' => [$person->id],
            'repair_params' => ['positionId' => Position::HQ_RUNNER]
        ]);

        $response->assertStatus(422);
    }

    public function testDeactivatedRepair()
    {
        $this->addRole(Role::ADMIN);
        $person = $this->person;

        Position::factory()->create([
            'id' => Position::HQ_LEAD,
            'title' => 'HQ Lead',
            'active' => 0
        ]);

        PersonPosition::factory()->create([
            'person_id' => $person->id,
            'position_id' => Position::HQ_LEAD
        ]);

        $response = $this->json('POST', 'position/repair', [
            'repair' => 'deactivated_positions',
            'people_ids' => [$person->id],
            'repair_params' => ['positionId' => Position::HQ_LEAD]
        ]);

        $response->assertStatus(200);
        $response->assertJsonCount(1, '*.id');
        $response->assertJson([
            [
                'id' => $person->id,
                'messages' => ['position removed']
            ]
        ]);

        $this->assertDatabaseMissing('person_position', [
            'person_id' => $person->id,
            'position_id' => Position::HQ_LEAD
        ]);
    }
}
