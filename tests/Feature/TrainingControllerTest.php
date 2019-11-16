<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

use App\Models\PersonPosition;
use App\Models\PersonSlot;
use App\Models\Position;
use App\Models\Role;
use App\Models\Slot;
use App\Models\TrainerStatus;

class TrainingControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    /*
     * have each test have a fresh user that is logged in.
     */

    public function setUp() : void
    {
        parent::setUp();
        $this->signInUser();
        $this->addRole(Role::TRAINER);
    }

    /*
     * Test the Trainer Attendance report
     */

    public function testTrainerAttendanceReport()
    {
        $personId = $this->user->id;

        factory(Position::class)->create([
            'id'    => Position::GREEN_DOT_TRAINER,
            'title' => 'Green Dot Trainer'
        ]);

        factory(Position::class)->create([
            'id'    => Position::GREEN_DOT_TRAINING,
            'title' => 'Green Dot Training',
            'type' => 'Training'
        ]);

        $traineeSlot = factory(Slot::class)->create([
            'begins' => date('Y-07-20 11:00:00'),
            'ends'   => date('Y-07-20 12:00:00'),
            'position_id'   => Position::GREEN_DOT_TRAINING,
            'description' => 'GD Training'
        ]);

        $trainerSlot = factory(Slot::class)->create([
            'begins' => date('Y-07-20 11:00:00'),
            'ends'   => date('Y-07-20 12:00:00'),
            'position_id'   => Position::GREEN_DOT_TRAINER,
            'description' => 'GD Training'
        ]);

        $futureTraineeSlot = factory(Slot::class)->create([
            'begins' => date('Y-12-31 23:00:00'),
            'ends'   => date('Y-12-31 23:01:00'),
            'position_id'   => Position::GREEN_DOT_TRAINING,
            'description' => 'New Years Training'
        ]);

        $futureTrainerSlot = factory(Slot::class)->create([
            'begins' => date('Y-12-31 23:00:00'),
            'ends'   => date('Y-12-31 23:01:00'),
            'position_id'   => Position::GREEN_DOT_TRAINER,
            'description' => 'New Years Training'
        ]);

        factory(PersonPosition::class)->create([
            'person_id' => $personId,
            'position_id' => Position::GREEN_DOT_TRAINER
        ]);

        factory(PersonSlot::class)->create([
            'person_id' => $personId,
            'slot_id' => $trainerSlot->id,
        ]);

        factory(PersonSlot::class)->create([
            'person_id' => $personId,
            'slot_id' => $futureTrainerSlot->id,
        ]);

        factory(TrainerStatus::class)->create([
            'person_id' => $personId,
            'slot_id'   => $traineeSlot->id,
            'trainer_slot_id' => $trainerSlot->id,
            'status'    => TrainerStatus::ATTENDED
        ]);

        $response = $this->json('GET', "training/".Position::GREEN_DOT_TRAINING."/trainer-attendance", [ 'year' => current_year() ]);
        $response->assertStatus(200);

        $response->assertJsonCount(1, 'trainers.*.id');
        $response->assertJson([
            'trainers' => [
                [
                    'id'    => $this->user->id,
                    'callsign' => $this->user->callsign,
                    'slots' => [
                        [
                            'id'    => $trainerSlot->id,
                            'begins' => (string) $trainerSlot->begins,
                            'description' => $trainerSlot->description,
                            'status'    => TrainerStatus::ATTENDED
                        ],
                        [
                            'id'    => $futureTrainerSlot->id,
                            'begins' => (string) $futureTrainerSlot->begins,
                            'description' => $futureTrainerSlot->description,
                            'status'    => TrainerStatus::PENDING
                        ]
                    ]
                ]
            ]
        ]);
    }
}
