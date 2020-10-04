<?php

namespace Tests\Feature\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

use App\Models\Person;
use App\Models\PersonPosition;
use App\Models\Position;
use App\Models\PersonMentor;
use App\Models\Role;

class PositionSanityCheckControllerTest extends TestCase
{
    use RefreshDatabase;

    public function setUp() : void
    {
        parent::setUp();
        $this->signInUser();

        $person = $this->person = Person::factory()->create([ 'callsign' => 'A Callsign' ]);
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
            'person_id'   => $person->id,
            'mentor_id'   => $this->user->id,
            'mentor_year' => $personYear,
            'status'      => 'pass'
        ]);

        // person has a Login Management position but no LM role
        PersonPosition::factory()->create([
            'person_id'    => $person->id,
            'position_id'  => Position::OPERATOR
        ]);

        Position::factory()->create([
            'id'   => Position::OPERATOR,
            'title' => 'Operator'
        ]);

        // Shiny Penny without the Dirt Shiny Position
        $this->shinyPenny = Person::factory()->create([ 'callsign' => 'B Callsign' ]);
        PersonMentor::factory()->create([
            'person_id'   => $this->shinyPenny->id,
            'mentor_id'   => $this->user->id,
            'mentor_year' => date('Y'),
            'status'      => 'pass'
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

        $response->assertJsonCount(1, 'green_dot.*.id');
        $response->assertJsonCount(1, 'management_role.*.id');
        $response->assertJsonCount(2, 'shiny_pennies.*.id');

        $response->assertJson([
            'green_dot' => [
                [
                    'id'   => $person->id,
                    'callsign' => $person->callsign,
                    'has_dirt_green_dot' => 1,
                    'has_sanctuary' => 0,
                    'has_gp_gd'=> 0
                ]
            ],

            'management_role' => [
                [
                    'id'   => $person->id,
                    'callsign' => $person->callsign,
                    'is_shiny_penny' => 0,
                    'positions' => [ [
                        'id'    => Position::OPERATOR,
                        'title' => 'Operator'
                    ] ]
                ]
            ],

            // Response is sorted by year descending, callsign
            'shiny_pennies' => [
                [
                    'id'   => $shinyPenny->id,
                    'callsign' => $shinyPenny->callsign,
                    'has_shiny_penny' => 0,
                    'year' => (int)date('Y'),
                ],
                [
                    'id'   => $person->id,
                    'callsign' => $person->callsign,
                    'has_shiny_penny' => 1,
                    'year' => $personYear,
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
        $personYear = $this->personYear;
        $shinyPenny = $this->shinyPenny;

        $response = $this->json('POST', 'position/repair', [ 'repair' => 'green_dot', 'people_ids' => [ $person->id ]]);
        $response->assertStatus(200);
        $response->assertJsonCount(1, '*.id');
        $response->assertJson([
            [
                'id'   => $person->id,
                'messages' => [ 'added Sanctuary', 'added Gerlach Patrol - Green Dot' ]
            ]
        ]);

        $this->assertDatabaseHas('person_position', [
            'person_id' => $person->id,
            'position_id' => Position::SANCTUARY
        ]);

        $this->assertDatabaseHas('person_position', [
            'person_id' => $person->id,
            'position_id' => Position::GERLACH_PATROL_GREEN_DOT
        ]);

        $response = $this->json('POST', 'position/repair', [ 'repair' => 'management_role', 'people_ids' => [ $person->id ]]);
        $response->assertStatus(200);
        $response->assertJsonCount(1, '*.id');
        $response->assertJson([[ 'id'   => $person->id ]]);

        $response = $this->json('POST', 'position/repair', [ 'repair' => 'shiny_pennies', 'people_ids' => [ $person->id, $shinyPenny->id ]]);
        $response->assertStatus(200);
        $response->assertJsonCount(2, '*.id');
        $response->assertJson([
            [
                'id'   => $person->id,
                'messages' => [ 'not a Shiny Penny, position removed' ]
            ],
            [
                'id'   => $shinyPenny->id,
                'messages' => [ 'is a Shiny Penny, position added' ]
            ]
        ]);

        $this->assertDatabaseMissing('person_position', [
            'person_id'    => $person->id,
            'position_id' => Position::DIRT_SHINY_PENNY
        ]);

        $this->assertDatabaseHas('person_position', [
            'person_id'    => $shinyPenny->id,
            'position_id' => Position::DIRT_SHINY_PENNY
        ]);
    }
}
