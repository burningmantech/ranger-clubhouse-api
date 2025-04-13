<?php

namespace Tests\Feature;

use App\Models\PersonAward;
use App\Models\Position;
use App\Models\Role;
use App\Models\Slot;
use App\Models\Timesheet;
use App\Models\TrainerStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PersonAwardControllerTest extends TestCase
{
    use RefreshDatabase;

    public PersonAward $award;

    public function setUp(): void
    {
        parent::setUp();

        $this->signInUser();
        Position::factory()->create([
            'id' => Position::DIRT,
            'title' => 'Dirt',
            'awards_eligible' => true,
        ]);

        $this->award = PersonAward::factory()->create([
            'person_id' => $this->user->id,
            'position_id' => Position::DIRT,
            'year' => 2019,
        ]);
    }

    /**
     * Check to see the awards retrieval is working.
     */

    public function testIndex(): void
    {
        $this->addRole(Role::AWARD_MANAGEMENT);

        $response = $this->get('person-award');
        $response->assertStatus(200);

        $award = $this->award;
        $response->assertJson([
            'person_award' => [
                [
                    'id' => $award->id,
                    'position_id' => $award->position_id,
                    'person_id' => $award->person_id,
                    'year' => $award->year,
                ]
            ]
        ]);
    }

    /**
     * Create an award
     */

    public function testCreateAward(): void
    {
        $this->addRole(Role::AWARD_MANAGEMENT);

        $data = [
            'person_id' => $this->user->id,
            'position_id' => Position::DIRT,
            'year' => 2018,
        ];
        $response = $this->post('person-award', ['person_award' => $data]);
        $response->assertStatus(200);
        $this->assertDatabaseHas('person_award', $data);
    }

    /**
     * Retrieve an award
     */

    public function testRetrieveAward(): void
    {
        $this->addRole(Role::AWARD_MANAGEMENT);

        $award = $this->award;
        $response = $this->get("person-award/{$award->id}");
        $response->assertStatus(200);
        $response->assertJson([
            'person_award' => [
                'id' => $award->id,
                'person_id' => $award->person_id,
                'position_id' => $award->position_id,
                'year' => $award->year,
            ]
        ]);
    }

    /**
     * Test update an award
     */

    public function testUpdateAward(): void
    {
        $this->addRole(Role::AWARD_MANAGEMENT);

        $award = $this->award;
        $response = $this->patch("person-award/{$award->id}", [
            'person_award' => [
                'notes' => 'Some notes!'
            ]
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('person_award', [
            'id' => $award->id,
            'notes' => 'Some notes!'
        ]);
    }

    /**
     * Test deleting an award
     */

    public function testDeleteAward(): void
    {
        $this->addRole(Role::AWARD_MANAGEMENT);
        $award = $this->award;
        $response = $this->delete("person-award/{$award->id}");
        $response->assertStatus(204);
        $this->assertDatabaseMissing('person_award', [
            'id' => $award->id,
        ]);
    }

    /**
     * Test award creation from timesheet creation
     */

    public function testCreateAwardFromTimesheet(): void
    {
        $this->addRole(Role::SHIFT_MANAGEMENT);

        $year = 2020;
        $onDuty = date("$year-08-25 06:00:00");
        $offDuty = date("$year-08-25 12:00:00");

        DB::table('position')->where('id', Position::DIRT)->update(['awards_auto_grant' => true]);
        $timesheet = Timesheet::factory()->create([
            'person_id' => $this->user->id,
            'on_duty' => $onDuty,
            'off_duty' => $offDuty,
            'position_id' => Position::DIRT,
        ]);

        $this->assertDatabaseHas('person_award', [
            'person_id' => $this->user->id,
            'position_id' => Position::DIRT,
            'year' => $year,
        ]);
    }

    /**
     * Test a timesheet should not generate an award
     */

    public function testNoAwardCreationFromTimesheet(): void
    {
        $this->addRole(Role::SHIFT_MANAGEMENT);
        Position::factory()->create([
            'id' => Position::DIRT_SHINY_PENNY,
            'title' => 'Dirt Shiny Penny',
            'awards_eligible' => false,
        ]);

        $year = 2021;
        $onDuty = date("$year-08-25 06:00:00");
        $offDuty = date("$year-08-25 12:00:00");

        $timesheet = Timesheet::factory()->create([
            'person_id' => $this->user->id,
            'on_duty' => $onDuty,
            'off_duty' => $offDuty,
            'position_id' => Position::DIRT_SHINY_PENNY,
        ]);

        $this->assertDatabaseMissing('person_award', [
            'person_id' => $this->user->id,
            'position_id' => Position::DIRT_SHINY_PENNY,
            'year' => $year,
        ]);
    }

    /**
     * Test award creation from trainer status update
     */

    public function testCreateAwardFromTrainerStatus(): void
    {
        Position::factory()->create([
            'id' => Position::TRAINER,
            'title' => 'Trainer',
            'type' => Position::TYPE_TRAINING,
            'awards_eligible' => true,
            'awards_auto_grant' => true,
        ]);

        Position::factory()->create([
            'id' => Position::TRAINING,
            'title' => 'Training',
            'type' => Position::TYPE_TRAINING,
        ]);

        $year = 2025;
        $begins = date("$year-08-25 06:00:00");
        $ends = date("$year-08-25 12:00:00");

        $trainingSlot = Slot::factory()->create([
            'position_id' => Position::TRAINING,
            'begins' => $begins,
            'ends' => $ends,
            'max' => 999,
        ]);

        $trainerSlot = Slot::factory()->create([
            'position_id' => Position::TRAINER,
            'begins' => $begins,
            'ends' => $ends,
            'max' => 999,
        ]);

        // Upon creation, an award will be issued.
        $trainerStatus = TrainerStatus::factory()->create([
            'person_id' => $this->user->id,
            'trainer_slot_id' => $trainerSlot->id,
            'slot_id' => $trainingSlot->id,
            'status' => TrainerStatus::ATTENDED,
        ]);

        $this->assertDatabaseHas('person_award', [
            'person_id' => $this->user->id,
            'position_id' => Position::TRAINER,
            'year' => $year,
        ]);
    }

    /**
     * Test basic bulk award uploads
     */

    const string BULK_AWARD_BASIC = ',position,Dirt,y,2025';

    public function test_basic_bulk_grant_awards(): void
    {
        $this->addRole(Role::AWARD_MANAGEMENT);
        $response = $this->post("person-award/bulk-grant", [
            'lines' => $this->user->callsign . self::BULK_AWARD_BASIC,
            'commit' => false
        ]);

        /*
        'awards' => [],
                'callsign' => $callsign,
                'columns' => $columns,
                'error' => null,
                'title' => '',
                'type' => '',
                'year' => [],
        */

        $response->assertStatus(200);
        $response->assertJson([
            'records' => [
                [
                    'callsign' => $this->user->callsign,
                    'awards' => [
                        [
                            'person_id' => $this->user->id,
                            'position_id' => Position::DIRT,
                            'year' => 2025,
                        ]
                    ]
                ]
            ]
        ]);

        $this->assertDatabaseMissing('person_award', [
            'person_id' => $this->user->id,
            'position_id' => Position::DIRT,
            'year' => 2025,
        ]);
    }

    /**
     * Test to see if an award was created via the bulk uploader.
     *
     * @return void
     */

    public function test_basic_bulk_grant_awards_with_commit(): void
    {
        $this->addRole(Role::AWARD_MANAGEMENT);
        $response = $this->post("person-award/bulk-grant", [
            'lines' => $this->user->callsign . self::BULK_AWARD_BASIC,
            'commit' => true
        ]);

        /*
        'awards' => [],
                'callsign' => $callsign,
                'columns' => $columns,
                'error' => null,
                'title' => '',
                'type' => '',
                'year' => [],
        */

        $response->assertStatus(200);
        $this->assertDatabaseHas('person_award', [
            'person_id' => $this->user->id,
            'position_id' => Position::DIRT,
            'year' => 2025,
        ]);
    }

    /**
     * Test to see if an award was created via the bulk uploader.
     *
     * @return void
     */

    public function test_bulk_grant_award_error_checking(): void
    {
        $this->addRole(Role::AWARD_MANAGEMENT);
        $callsign = $this->user->callsign;
        $response = $this->post("person-award/bulk-grant", [
            'lines' => <<<__EOF__
                bad-callsign,position,Dirt,Y,2025
                $callsign,unknown,Dirt,Y,2025
                $callsign,position,Bad Title,Y,2025
                $callsign,position,Dirt,Y,20
                $callsign,position,Dirt,Y,2025-2020
                $callsign,position,Dirt,Y,2199
                $callsign,position,Dirt,Y
                $callsign,position,Dirt,why,2025
                __EOF__,
            'commit' => true
        ]);

        /*
        'awards' => [],
                'callsign' => $callsign,
                'columns' => $columns,
                'error' => null,
                'title' => '',
                'type' => '',
                'year' => [],
        */

        DB::table('person_award')->delete();

        $response->assertStatus(200);
        $response->assertJson([
            'records' => [
                [
                    'callsign' => 'bad-callsign',
                    'error' => 'Callsign not found',
                ],
                [
                    'error' => 'Type is neither award, position, or team.',
                ],
                [
                    'error' => 'Position "Bad Title" not found',
                ],
                [
                    'error' => 'year 20 is before 1996',
                ],
                [
                    'error' => 'Start year is after ending year'
                ],
                [
                    'error' => 'Year 2199 is in the future. Current year is only ' . current_year(),
                ],
                [
                    'error' => 'No award year(s) given.',
                ],
                [
                    'error' => 'Award year indicator "why" is neither y nor n',
                ]
            ]
        ]);

        $this->assertDatabaseCount('person_award', 0);
    }
}
