<?php

namespace Tests\Feature;

use App\Models\PersonAward;
use App\Models\Position;
use App\Models\Role;
use App\Models\Slot;
use App\Models\Timesheet;
use App\Models\TrainerStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function testCreateAwardFromTimesheet() : void
    {
        $this->addRole(Role::SHIFT_MANAGEMENT);

        $year = 2020;
        $onDuty = date("$year-08-25 06:00:00");
        $offDuty = date("$year-08-25 12:00:00");

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
     * Test award creation from trainer status update
     */

    public function testCreateAwardFromTrainerStatus() : void
    {
        Position::factory()->create([
            'id' => Position::TRAINER,
            'title' => 'Trainer',
            'type' => Position::TYPE_TRAINING,
            'awards_eligible' => true,
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
}
