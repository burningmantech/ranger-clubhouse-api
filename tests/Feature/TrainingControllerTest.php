<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

use App\Models\Person;
use App\Models\PersonPosition;
use App\Models\PersonSlot;
use App\Models\Position;
use App\Models\Role;
use App\Models\Slot;
use App\Models\TraineeStatus;
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
        $this->addRole(Role::ART_TRAINER_BASE | Position::GREEN_DOT_TRAINING);
        $personId = $this->user->id;

        Position::factory()->create([
            'id'    => Position::GREEN_DOT_TRAINER,
            'title' => 'Green Dot Trainer'
        ]);

        Position::factory()->create([
            'id'    => Position::GREEN_DOT_TRAINING,
            'title' => 'Green Dot Training',
            'type' => 'Training'
        ]);

        $traineeSlot = Slot::factory()->create([
            'begins' => date('Y-07-20 11:00:00'),
            'ends'   => date('Y-07-20 12:00:00'),
            'position_id'   => Position::GREEN_DOT_TRAINING,
            'description' => 'GD Training'
        ]);

        $trainerSlot = Slot::factory()->create([
            'begins' => date('Y-07-20 11:00:00'),
            'ends'   => date('Y-07-20 12:00:00'),
            'position_id'   => Position::GREEN_DOT_TRAINER,
            'description' => 'GD Training'
        ]);

        $futureTraineeSlot = Slot::factory()->create([
            'begins' => date('Y-12-31 23:00:00'),
            'ends'   => date('Y-12-31 23:01:00'),
            'position_id'   => Position::GREEN_DOT_TRAINING,
            'description' => 'New Years Training'
        ]);

        $futureTrainerSlot = Slot::factory()->create([
            'begins' => date('Y-12-31 23:00:00'),
            'ends'   => date('Y-12-31 23:01:00'),
            'position_id'   => Position::GREEN_DOT_TRAINER,
            'description' => 'New Years Training'
        ]);

        PersonPosition::factory()->create([
            'person_id' => $personId,
            'position_id' => Position::GREEN_DOT_TRAINER
        ]);

        PersonSlot::factory()->create([
            'person_id' => $personId,
            'slot_id' => $trainerSlot->id,
        ]);

        PersonSlot::factory()->create([
            'person_id' => $personId,
            'slot_id' => $futureTrainerSlot->id,
        ]);

        TrainerStatus::factory()->create([
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

    /*
     * Test People Training Completed report includes trainers
     */

    public function testPeopleTrainingCompleted()
    {
        $this->addRole(Role::ART_TRAINER_BASE | Position::GREEN_DOT_TRAINING);

        Position::factory()->create([
            'id' => Position::GREEN_DOT_TRAINER,
            'title' => 'Green Dot Trainer',
        ]);

        Position::factory()->create([
            'id' => Position::GREEN_DOT_TRAINING,
            'title' => 'Green Dot Training',
            'type' => 'Training',
        ]);

        $trainingSlot = Slot::factory()->create([
            'begins' => date('Y-07-20 11:00:00'),
            'ends' => date('Y-07-20 12:00:00'),
            'position_id' => Position::GREEN_DOT_TRAINING,
            'description' => 'GD Training',
        ]);

        $trainerSlot = Slot::factory()->create([
            'begins' => date('Y-07-20 11:00:00'),
            'ends' => date('Y-07-20 12:00:00'),
            'position_id' => Position::GREEN_DOT_TRAINER,
            'description' => 'GD Training',
        ]);

        $trainee = Person::factory()->create();
        $trainer = Person::factory()->create();
        $pendingTrainer = Person::factory()->create();

        TraineeStatus::factory()->create([
            'person_id' => $trainee->id,
            'slot_id' => $trainingSlot->id,
            'passed' => true,
        ]);

        TrainerStatus::factory()->create([
            'person_id' => $trainer->id,
            'slot_id' => $trainingSlot->id,
            'trainer_slot_id' => $trainerSlot->id,
            'status' => TrainerStatus::ATTENDED,
        ]);

        TrainerStatus::factory()->create([
            'person_id' => $pendingTrainer->id,
            'slot_id' => $trainingSlot->id,
            'trainer_slot_id' => $trainerSlot->id,
            'status' => TrainerStatus::PENDING,
        ]);

        $response = $this->json('GET', 'training/' . Position::GREEN_DOT_TRAINING . '/people-training-completed', ['year' => current_year()]);
        $response->assertStatus(200);

        $response->assertJsonCount(1, 'slots');
        $response->assertJsonCount(1, 'slots.0.people');
        $response->assertJsonCount(1, 'slots.0.trainers');

        $response->assertJson([
            'slots' => [
                [
                    'slot_id' => $trainingSlot->id,
                    'people' => [
                        [
                            'id' => $trainee->id,
                            'callsign' => $trainee->callsign,
                        ],
                    ],
                    'trainers' => [
                        [
                            'id' => $trainer->id,
                            'callsign' => $trainer->callsign,
                        ],
                    ],
                ],
            ],
        ]);
    }
}
