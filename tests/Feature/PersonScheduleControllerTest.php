<?php

namespace Tests\Feature;

use App\Models\PersonOnlineTraining;
use Mockery;
use Tests\TestCase;

use Carbon\Carbon;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

use App\Models\Person;
use App\Models\PersonPhoto;
use App\Models\PersonPosition;
use App\Models\PersonSlot;
use App\Models\Position;
use App\Models\Role;
use App\Models\Slot;

use Illuminate\Support\Facades\Queue;

use App\Mail\TrainingSessionFullMail;
use App\Jobs\TrainingSignupEmailJob;

class PersonScheduleControllerTest extends TestCase
{
    use RefreshDatabase;

    public $dirtPosition;
    public $dirtSlots;

    public $greenDotDirtPosition;
    public $greenDotSlots;
    public $greenDotTrainingPosition;
    public $greenDotTrainingSlots;

    public $trainingPosition;
    public $trainingSlots;

    public $year;

    public static function setUpBeforeClass(): void
    {

    }

    /*
     * have each test have a fresh user that is logged in,
     * and a set of positions.
     */

    public function setUp(): void
    {
        parent::setUp();

        $this->setting('OnlineTrainingDisabledAllowSignups', false);
        $this->signInUser();

        // scheduling ends up sending lots of emails..
        Mail::fake();

        $year = $this->year = date('Y');

        // Setup default (real world) positions
        $this->trainingPosition = Position::factory()->create(
            [
                'id' => Position::TRAINING,
                'title' => 'Training',
                'type' => 'Training',
                'contact_email' => 'training-academy@example.com',
                'prevent_multiple_enrollments' => true,
            ]
        );

        $this->dirtPosition = Position::factory()->create(
            [
                'id' => Position::DIRT,
                'title' => 'Dirt',
                'type' => 'Frontline',
            ]
        );

        // Used for ART training & signups
        $this->greenDotTrainingPosition = Position::factory()->create(
            [
                'id' => Position::GREEN_DOT_TRAINING,
                'title' => 'Green Dot - Training',
                'type' => 'Training',
                'contact_email' => 'greendots@example.com',
                'prevent_multiple_enrollments' => true,
            ]
        );

        $this->greenDotDirtPosition = Position::factory()->create(
            [
                'id' => Position::DIRT_GREEN_DOT,
                'title' => 'Green Dot - Dirt',
                'type' => 'Frontline',
                'training_position_id' => $this->greenDotTrainingPosition->id,
            ]
        );

        $this->trainingSlots = [];
        for ($i = 0; $i < 3; $i++) {
            $day = (25 + $i);
            $this->trainingSlots[] = Slot::factory()->create(
                [
                    'begins' => date("$year-05-$day 09:45:00"),
                    'ends' => date("$year-05-$day 17:45:00"),
                    'position_id' => Position::TRAINING,
                    'description' => "Training #$i",
                    'signed_up' => 0,
                    'max' => 10,
                    'min' => 0,
                ]
            );
        }

        $this->dirtSlots = [];
        for ($i = 0; $i < 3; $i++) {
            $day = (25 + $i);
            $this->dirtSlots[] = Slot::factory()->create(
                [
                    'begins' => date("$year-08-$day 09:45:00"),
                    'ends' => date("$year-08-$day 17:45:00"),
                    'position_id' => Position::DIRT,
                    'description' => "Dirt #$i",
                    'signed_up' => 0,
                    'max' => 10,
                    'min' => 0,
                ]
            );
        }

        $this->greenDotTrainingSlots = [];
        for ($i = 0; $i < 3; $i++) {
            $day = (25 + $i);
            $this->greenDotTrainingSlots[] = Slot::factory()->create(
                [
                    'begins' => date("$year-06-$day 09:45:00"),
                    'ends' => date("$year-06-$day 17:45:00"),
                    'position_id' => Position::GREEN_DOT_TRAINING,
                    'description' => "GD Training #$i",
                    'signed_up' => 0,
                    'max' => 10,
                    'min' => 0,
                ]
            );
        }

        $this->greenDotSlots = [];
        for ($i = 0; $i < 3; $i++) {
            $day = (25 + $i);
            $this->greenDotSlots[] = Slot::factory()->create(
                [
                    'begins' => date("$year-08-$day 09:45:00"),
                    'ends' => date("$year-08-$day 17:45:00"),
                    'position_id' => Position::DIRT_GREEN_DOT,
                    'description' => "Green Dot #$i",
                    'signed_up' => 0,
                    'max' => 10,
                    'min' => 0,
                ]
            );
        }
    }

    public function createPerson()
    {
        $person = Person::factory()->create();
        PersonPosition::factory()->create([
            'person_id' => $person->id,
            'position_id' => Position::TRAINING
        ]);

        return $person;
    }

    public function setupRequirements($person)
    {
        $this->mockOnlineTrainingPass($person);
        $this->setupPhotoStatus(PersonPhoto::APPROVED, $person);
    }

    private function setupPhotoStatus($status, $person = null)
    {
        if ($person == null) {
            $person = $this->user;
        }

        $photo = PersonPhoto::factory()->create([
            'person_id' => $person->id,
            'status' => $status,
        ]);

        $person->person_photo_id = $photo->id;
        $person->saveWithoutValidation();
    }

    private function mockOnlineTrainingPass($person)
    {
        $ot = new PersonOnlineTraining;
        $ot->person_id = $person->id;
        $ot->completed_at = now();
        $ot->type = PersonOnlineTraining::DOCEBO;
        $ot->saveWithoutValidation();
    }


    /*
     * Find only the signups for a year.
     */

    public function testFindOnlyShiftSignupsForAYear()
    {
        $personId = $this->user->id;

        $this->addPosition(Position::TRAINING);
        $this->addPosition(Position::DIRT);

        $slotId = $this->dirtSlots[0]->id;

        PersonSlot::factory()->create(
            [
                'person_id' => $personId,
                'slot_id' => $slotId,
            ]
        );

        $response = $this->json('GET', "person/{$this->user->id}/schedule", ['year' => $this->year]);
        $response->assertStatus(200);

        $response->assertJsonStructure(['schedules' => [['id']]]);
        $this->assertCount(1, $response->json()['schedules']);
        $this->assertEquals($slotId, $response->json()['schedules'][0]['id']);
    }


    /*
     * Find the available shifts and signups for a year.
     */

    public function testFindAvailableShiftsAndSignupsForYear()
    {
        $personId = $this->user->id;

        $this->addPosition(Position::TRAINING);
        $this->addPosition(Position::DIRT);

        $slotId = $this->dirtSlots[0]->id;

        PersonSlot::factory()->create(
            [
                'person_id' => $personId,
                'slot_id' => $slotId,
            ]
        );

        $response = $this->json('GET', "person/{$this->user->id}/schedule", ['year' => $this->year, 'shifts_available' => 1]);
        $response->assertStatus(200);

        // Should match 6 shifts - 3 trainings and 3 dirt shift
        $this->assertCount(6, $response->json()['schedules']);
    }


    /*
     * Fail to find any shifts for the year
     */

    public function testDoNotFindAnyShiftsForYear()
    {
        $response = $this->json('GET', "person/{$this->user->id}/schedule", ['year' => $this->year, 'shifts_available' => 1]);
        $response->assertStatus(200);
        $response->assertJson(['schedules' => []]);
    }


    /*
     * Successfully signup for a dirt shift
     */

    public function testSignupForDirtShift()
    {
        $this->addPosition(Position::DIRT);
        $shift = $this->dirtSlots[0];
        $personId = $this->user->id;

        $this->setupRequirements($this->user);

        $response = $this->json(
            'POST',
            "person/$personId/schedule",
            [
                'slot_id' => $shift->id,
            ]
        );

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        $this->assertDatabaseHas(
            'person_slot',
            [
                'person_id' => $personId,
                'slot_id' => $shift->id,
            ]
        );

        /*
        $to = $this->user->email;

        Mail::assertSent(
            SlotSignup::class,
            function ($mail) use ($to) {
                return $mail->hasTo($to);
            }
        );*/
    }


    /*
     * Successfully signup for a training shift and email sent
     */

    public function testSignupForTrainingSession()
    {
        $this->addPosition(Position::TRAINING);
        $shift = $this->trainingSlots[0];
        $personId = $this->user->id;

        $this->setupRequirements($this->user);

        Queue::fake();

        $response = $this->json(
            'POST',
            "person/{$personId}/schedule",
            [
                'slot_id' => $shift->id,
            ]
        );

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        $this->assertDatabaseHas(
            'person_slot',
            [
                'person_id' => $personId,
                'slot_id' => $shift->id,
            ]
        );

        // Training should not be overcapacity
        Mail::assertNotQueued(TrainingSessionFullMail::class);

        Queue::assertPushed(TrainingSignupEmailJob::class,
            function ($job) use ($personId, $shift) {
                return $job->person->id == $personId && $job->slot->id = $shift->id;
            }
        );
    }


    /*
     * Successfully signup for a training shift and email T.A. session is full
     */

    public function testAlertTrainingAcademyWhenTrainingSessionIsFull()
    {
        $this->setupRequirements($this->user);
        $this->addPosition(Position::TRAINING);
        $shift = $this->trainingSlots[0];
        $shift->update(['max' => 1]);

        $response = $this->json(
            'POST',
            "person/{$this->user->id}/schedule",
            [
                'slot_id' => $shift->id,
            ]
        );

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        Mail::assertQueued(TrainingSessionFullMail::class, 1);
    }


    /*
     * Failure to signup for a shift with no position
     */

    public function testDoNotAllowShiftSignupWithNoPosition()
    {
        $this->setupRequirements($this->user);
        $shift = $this->greenDotSlots[0];

        $response = $this->json(
            'POST',
            "person/{$this->user->id}/schedule",
            [
                'slot_id' => $shift->id,
            ]
        );

        $response->assertStatus(200);
        $response->assertJson(['status' => 'no-position']);

        $this->assertDatabaseMissing(
            'person_slot',
            [
                'person_id' => $this->user->id,
                'slot_id' => $shift->id,
            ]
        );
    }


    /*
     * Prevent sign up for a full shift
     */

    public function testPreventSignupForFullShift()
    {
        $this->setupRequirements($this->user);
        $this->addPosition(Position::TRAINING);
        $shift = $this->trainingSlots[0];
        $shift->update(['signed_up' => 1, 'max' => 1]);

        $response = $this->json(
            'POST',
            "person/{$this->user->id}/schedule",
            [
                'slot_id' => $shift->id,
            ]
        );

        $response->assertStatus(200);
        $response->assertJson(['status' => 'full']);

        $this->assertDatabaseMissing(
            'person_slot',
            [
                'person_id' => $this->user->id,
                'slot_id' => $shift->id,
            ]
        );
    }

    /*
     * Prevent sign up for a started shift
     */

    public function testPreventSignupForStartedShift()
    {
        $this->setupRequirements($this->user);
        $shift = $this->trainingSlots[0];
        $shift->update(['signed_up' => 1, 'max' => 1, 'begins' => date('2000-08-25 12:00:00')]);
        $this->addPosition(Position::TRAINING);

        $response = $this->json(
            'POST',
            "person/{$this->user->id}/schedule",
            [
                'slot_id' => $shift->id,
            ]
        );

        $response->assertStatus(200);
        $response->assertJson(['status' => 'has-started']);

        $this->assertDatabaseMissing(
            'person_slot',
            [
                'person_id' => $this->user->id,
                'slot_id' => $shift->id,
            ]
        );
    }

    /*
     * Allow sign up for a started shift when Admin
     */

    public function testAllowSignupForStartedShiftIfAdmin()
    {
        $this->setupRequirements($this->user);
        $this->addRole(Role::ADMIN);

        $person = Person::factory()->create();
        $this->addPosition(Position::DIRT, $person);
        $shift = $this->dirtSlots[0];
        $err = $shift->update(['signed_up' => 1, 'max' => 1, 'begins' => date('2000-08-25 12:00:00')]);

        $response = $this->json(
            'POST',
            "person/{$person->id}/schedule",
            ['slot_id' => $shift->id, 'force' => 1]
        );

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        $this->assertDatabaseHas(
            'person_slot',
            [
                'person_id' => $person->id,
                'slot_id' => $shift->id,
            ]
        );
    }

    /*
     * Allow a trainer to add a person to past training shift
     */

    public function testDenySignupForPastShiftForTrainer()
    {
        $this->addRole(Role::TRAINER);

        $person = $this->createPerson();
        $this->setupRequirements($person);
        $personId = $person->id;

        $training = $this->trainingSlots[0];
        $training->update(['begins' => date('2010-08-25 10:00:00')]);

        $response = $this->json(
            'POST',
            "person/{$personId}/schedule",
            ['slot_id' => $training->id, 'force' => true]
        );

        $response->assertStatus(200);
        $response->assertJson(['status' => 'has-started']);

        $this->assertDatabaseMissing(
            'person_slot',
            [
                'person_id' => $personId,
                'slot_id' => $training->id,
            ]
        );
    }

    /*
     * Force a full shift signup by admin user
     */

    public function testMayForceSignupForFullShiftIfAdmin()
    {
        $person = Person::factory()->create();
        $this->setupRequirements($person);
        $this->addPosition(Position::TRAINING, $person);
        $this->addRole(Role::ADMIN);

        $shift = $this->trainingSlots[0];
        $shift->update(['signed_up' => 1, 'max' => 1,]);

        $response = $this->json(
            'POST',
            "person/{$person->id}/schedule",
            [
                'slot_id' => $shift->id,
            ]
        );

        $response->assertStatus(200);
        $response->assertJson(['status' => 'full', 'may_force' => true]);

        $this->assertDatabaseMissing(
            'person_slot',
            [
                'person_id' => $person->id,
                'slot_id' => $shift->id,
            ]
        );
    }

    /*
     * Force a full shift signup by admin user
     */

    public function testAllowSignupForFullShiftIfAdmin()
    {
        $person = Person::factory()->create();
        $this->setupRequirements($person);
        $this->addPosition(Position::TRAINING, $person);
        $this->addRole(Role::ADMIN);

        $shift = $this->trainingSlots[0];
        $shift->update(['signed_up' => 1, 'max' => 1]);

        $response = $this->json(
            'POST',
            "person/{$person->id}/schedule",
            [
                'slot_id' => $shift->id,
                'force' => 1,
            ]
        );

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success', 'overcapacity' => true]);

        $this->assertDatabaseHas(
            'person_slot',
            [
                'person_id' => $person->id,
                'slot_id' => $shift->id,
            ]
        );
    }


    /*
     * Prevent user from signing up for multiple training sessions
     */

    public function testPreventMultipleEnrollmentsForTrainingSessions()
    {
        $personId = $this->user->id;
        $this->setupRequirements($this->user);
        $this->addPosition(Position::TRAINING);

        $previousTraining = $this->trainingSlots[0];

        PersonSlot::factory()->create(
            [
                'person_id' => $personId,
                'slot_id' => $previousTraining->id,
            ]
        );

        $attemptedTraining = $this->trainingSlots[1];

        $response = $this->json(
            'POST',
            "person/{$personId}/schedule",
            [
                'slot_id' => $attemptedTraining->id,
            ]
        );

        $response->assertStatus(200);
        $response->assertJson(['status' => 'multiple-enrollment']);

        $this->assertDatabaseMissing(
            'person_slot',
            [
                'person_id' => $personId,
                'slot_id' => $attemptedTraining->id,
            ]
        );
    }

    /*
     * Alow the user to sign up for multiple part training sessions
     */

    public function testAllowMultiplePartTrainingSessionsSignup()
    {
        $personId = $this->user->id;
        $this->setupRequirements($this->user);
        $this->addPosition(Position::TRAINING);

        $year = date('Y') + 1;

        $part1 = Slot::factory()->create([
            'description' => 'Elysian Fields - Part 1',
            'position_id' => Position::TRAINING,
            'begins' => date("$year-08-30 12:00:00"),
            'ends' => date("$year-08-30 18:00:00")
        ]);

        $part2 = Slot::factory()->create([
            'description' => 'Elysian Fields - Part 2',
            'position_id' => Position::TRAINING,
            'begins' => date("$year-08-31 12:00:00"),
            'ends' => date("$year-08-31 18:00:00")
        ]);

        PersonSlot::factory()->create(
            [
                'person_id' => $personId,
                'slot_id' => $part1->id,
            ]
        );

        $response = $this->json(
            'POST',
            "person/{$personId}/schedule",
            ['slot_id' => $part2->id]
        );

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        $this->assertDatabaseHas(
            'person_slot',
            [
                'person_id' => $this->user->id,
                'slot_id' => $part2->id,
            ]
        );
    }

    /*
     * Allow person to be signed up to multiple trainings for admin
     */

    public function testAllowMultipleEnrollmentsForTrainingSessionsIfAdmin()
    {
        $this->addRole(Role::ADMIN);

        $person = Person::factory()->create();
        $this->setupRequirements($person);

        $this->addPosition(Position::TRAINING, $person);
        $personId = $person->id;

        $previousTraining = $this->trainingSlots[0];

        PersonSlot::factory()->create(
            [
                'person_id' => $personId,
                'slot_id' => $previousTraining->id,
            ]
        );

        $attemptedTraining = $this->trainingSlots[1];

        $response = $this->json(
            'POST',
            "person/{$personId}/schedule",
            [
                'slot_id' => $attemptedTraining->id,
                'force' => true
            ]
        );

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success', 'multiple_enrollment' => true]);

        $this->assertDatabaseHas(
            'person_slot',
            [
                'person_id' => $personId,
                'slot_id' => $attemptedTraining->id,
            ]
        );
    }


    /*
     * Deny a trainer to sign some one else up multiple time to a training session.
     */

    public function testDenyMultipleEnrollmentsForTrainingSessionsForTrainers()
    {
        $this->addRole(Role::TRAINER);

        $person = $this->createPerson();
        $this->setupRequirements($person);

        $personId = $person->id;
        $previousTraining = $this->trainingSlots[0];

        PersonSlot::factory()->create(
            [
                'person_id' => $personId,
                'slot_id' => $previousTraining->id,
            ]
        );

        $attemptedTraining = $this->trainingSlots[1];

        $response = $this->json(
            'POST',
            "person/{$personId}/schedule",
            ['slot_id' => $attemptedTraining->id]
        );

        $response->assertStatus(200);
        $response->assertJson(['status' => 'multiple-enrollment']);

        $this->assertDatabaseMissing(
            'person_slot',
            [
                'person_id' => $personId,
                'slot_id' => $attemptedTraining->id,
            ]
        );
    }

    /*
     * Remove a signup
     */

    public function testDeleteSignupSuccess()
    {
        $shift = $this->dirtSlots[0];
        $personId = $this->user->id;
        $personSlot = [
            'person_id' => $personId,
            'slot_id' => $shift->id,
        ];

        PersonSlot::factory()->create($personSlot);

        $response = $this->json('DELETE', "person/{$personId}/schedule/{$shift->id}");
        $response->assertStatus(200);
        $this->assertDatabaseMissing('person_slot', $personSlot);
    }

    /*
     * Prevent a signup removal if the shift already started.
     */

    public function testPreventDeleteSignup()
    {
        $shift = $this->dirtSlots[0];
        $shift->update(['begins' => date('2000-01-01 12:00:00')]);
        $personId = $this->user->id;
        $personSlot = [
            'person_id' => $personId,
            'slot_id' => $shift->id,
        ];

        PersonSlot::factory()->create($personSlot);

        $response = $this->json('DELETE', "person/{$personId}/schedule/{$shift->id}");
        $response->assertStatus(200);
        $response->assertJson(['status' => 'has-started']);
        $this->assertDatabaseHas('person_slot', $personSlot);
    }

    /*
     * Allow a signup removal for past shift when admin
     */

    public function testAllowDeleteSignupIfAdmin()
    {
        $shift = $this->dirtSlots[0];
        $shift->update(['begins' => date('2000-01-01 12:00:00')]);
        $personId = $this->user->id;
        $personSlot = [
            'person_id' => $personId,
            'slot_id' => $shift->id,
        ];

        PersonSlot::factory()->create($personSlot);

        $this->addRole(Role::ADMIN);

        $response = $this->json('DELETE', "person/{$personId}/schedule/{$shift->id}");
        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);
        $this->assertDatabaseMissing('person_slot', $personSlot);
    }

    /*
     * Fail to delete a non-existent sign up.
     */

    public function testFailWhenDeletingNonExistentSignup()
    {
        $shift = $this->dirtSlots[0];
        $personId = $this->user->id;

        $response = $this->json('DELETE', "person/{$personId}/schedule/{$shift->id}");
        $response->assertStatus(404);
    }

    /*
     * Allow an active, who completed Online Training, and has a photo to sign up.
     */

    public function testAllowActiveWhoCompletedOnlineTrainingAndHasPhoto()
    {
        $photoMock = $this->setupPhotoStatus('approved');
        $mrMock = $this->mockOnlineTrainingPass($this->user);

        $response = $this->json('GET', "person/{$this->user->id}/schedule/permission", [
            'year' => $this->year
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'permission' => [
                'all_signups_allowed' => true,
            ]
        ]);
    }

    /*
     * Deny an active, who completed Online Training, and has no photo.
     */

    public function testDenyActiveWithNoPhoto()
    {
        $mrMock = $this->mockOnlineTrainingPass($this->user);

        $response = $this->json('GET', "person/{$this->user->id}/schedule/permission", [
            'year' => $this->year
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'permission' => [
                'all_signups_allowed' => false,
                'requirements' => ['photo-unapproved']
            ]
        ]);
    }

    /*
     * Deny an active, who has photo, and did not complete Online Training
     */

    public function testDenyActiveWhoDidNotCompleteOnlineTraining()
    {
        $this->setupPhotoStatus('approved');

        $response = $this->json('GET', "person/{$this->user->id}/schedule/permission", [
            'year' => $this->year
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'permission' => [
                'training_signups_allowed' => false,
            ]
        ]);
    }

    /*
     * Deny an active who did not sign the behavioral standards agreement
     */

    /*
        public function testDenyActiveWhoDidNotSignBehavioralAgreement()
        {
            $photoMock = $this->setupPhotoStatus('approved');
            $mrMock = $this->mockOnlineTrainingPass(true);
            $this->user->update([ 'behavioral_agreement' => false ]);
            $response = $this->json('GET', "person/{$this->user->id}/schedule/permission", [
                   'year' => $this->year
               ]);

            $response->assertStatus(200);
            $response->assertJson([
                   'permission'   => [
                       'signup_allowed'       => false,
                       'missing_behavioral_agreement' => true,
                   ]
               ]);
        }
        */

    /*
     * Warn user the behavioral agreement was not agreed to but allow sign ups.
     */

    /*

    TODO: uncomment when agreement language has been updated.
    public function testMarkActiveWhoDidNotSignBehavioralAgreement()
    {
        $person = Person::factory()->create(['behavioral_agreement' => false]);
        $this->actingAs($person); // login
        $this->setupRequirements($person);

        $response = $this->json('GET', "person/{$person->id}/schedule/permission", [
            'year' => $this->year
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'permission' => [
                'signup_allowed' => true,
                'missing_behavioral_agreement' => true,
            ]
        ]);
    }
    */

    /*
     * Allow an auditor, who completed Online Training, and has no photo to sign up.
     */

    public function testAllowAuditorWithNoPhotoAndCompletedOnlineTraining()
    {
        $person = Person::factory()->create(['status' => Person::AUDITOR, 'reviewed_pi_at' => now()]);
        $this->actingAs($person);
        $this->mockOnlineTrainingPass($person);

        $response = $this->json('GET', "person/{$person->id}/schedule/permission", [
            'year' => $this->year
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'permission' => [
                'all_signups_allowed' => true,
            ]
        ]);
    }

    /*
     * Limit how many people can sign up for a shift when a trainer's slot
     * has been set. max becomes a multiplier.
     */

    public function testSignUpLimitWithTrainingSlot()
    {
        $this->setupRequirements($this->user);
        $this->addPosition(Position::TRAINING);

        $shift = $this->trainingSlots[0];
        $trainerSlot = Slot::factory()->create(
            [
                'begins' => date($shift->begins),
                'ends' => date($shift->ends),
                'position_id' => Position::TRAINER,
                'description' => "Trainers",
                'signed_up' => 2,
                'max' => 2,
                'min' => 0,
            ]
        );

        $trainer1 = Person::factory()->create();
        PersonSlot::factory()->create(['person_id' => $trainer1->id, 'slot_id' => $trainerSlot->id]);
        $trainer2 = Person::factory()->create();
        PersonSlot::factory()->create(['person_id' => $trainer2->id, 'slot_id' => $trainerSlot->id]);

        $shift->update(['signed_up' => 1, 'max' => 1, 'trainer_slot_id' => $trainerSlot->id]);

        $response = $this->json(
            'POST',
            "person/{$this->user->id}/schedule",
            [
                'slot_id' => $shift->id,
            ]
        );

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        $this->assertDatabaseHas(
            'person_slot',
            [
                'person_id' => $this->user->id,
                'slot_id' => $shift->id,
            ]
        );
    }

    /**
     * Make sure the two annotations
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * are used when calling this mock.
     */

    private function mockBurnWeekend()
    {
        $mock = Mockery::mock('alias:App\Models\EventDate');
        $mock->shouldReceive('retrieveBurnWeekendPeriod')
            ->andReturn([now(), now()->addHours(6)]);
    }

    /**
     * Recommend working a Burn Weekend shift is none is present in the schedule
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */

    public function testRecommendBurnWeekendShift()
    {
        $this->mockBurnWeekend();

        $year = $this->year;

        $this->setupRequirements($this->user);

        // Check for scheduling
        $response = $this->json('GET', "person/{$this->user->id}/schedule/permission", ['year' => $year]);

        $response->assertStatus(200);
        $response->assertJson([
            'permission' => [
                'recommend_burn_weekend_shift' => true
            ]
        ]);

        // And the HQ interface
        $response = $this->json('GET', "person/{$this->user->id}/schedule/recommendations");

        $response->assertStatus(200);
        $response->assertJson(['burn_weekend_shift' => true]);
    }

    /**
     * Do NOT recommend working a Burn Weekend shift is none is present in the schedule for a non ranger
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */

    public function testDoNotRecommendBurnWeekendShiftForNonRanger()
    {
        $this->mockBurnWeekend();
        $year = $this->year;

        $person = Person::factory()->create(['status' => Person::NON_RANGER]);
        $this->actingAs($person);
        $photoMock = $this->setupPhotoStatus('approved', $person);

        // Check for scheduling
        $response = $this->json('GET', "person/{$person->id}/schedule/permission", ['year' => $year]);

        $response->assertStatus(200);
        $response->assertJson([
            'permission' => [
                'recommend_burn_weekend_shift' => false
            ]
        ]);

        // And the HQ interface
        $response = $this->json('GET', "person/{$person->id}/schedule/recommendations");

        $response->assertStatus(200);
        $response->assertJson(['burn_weekend_shift' => false]);
    }

    /**
     * Do not recommend working a Burn Weekend shift because the person is signed up for a Burn Weekend shift.
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */

    public function testDoNotRecommendBurnWeekendShift()
    {
        $this->mockBurnWeekend();
        $year = $this->year;

        $this->setupRequirements($this->user);

        $shift = Slot::factory()->create(
            [
                'begins' => (string)now(),
                'ends' => (string)now()->addHours(6),
                'position_id' => Position::DIRT,
                'description' => "BURN WEEKEND BABY!",
                'signed_up' => 0,
                'max' => 10,
                'min' => 0,
            ]
        );

        PersonSlot::factory()->create([
            'person_id' => $this->user->id,
            'slot_id' => $shift->id
        ]);

        // Check for scheduling
        $response = $this->json('GET', "person/{$this->user->id}/schedule/permission", ['year' => $year]);

        $response->assertStatus(200);
        $response->assertJson([
            'permission' => [
                'recommend_burn_weekend_shift' => false
            ]
        ]);

        // And check the HQ interface
        $response = $this->json('GET', "person/{$this->user->id}/schedule/recommendations");

        $response->assertStatus(200);
        $response->assertJson(['burn_weekend_shift' => false]);
    }

    public function testScheduleLogSuccess()
    {
        $this->addPosition(Position::DIRT);
        $this->addRole(Role::MANAGE);
        $shift = $this->dirtSlots[0];
        $personId = $this->user->id;
        $callsign = $this->user->callsign;

        $this->setupRequirements($this->user);

        // Sign up, and then remove the sign up so a audit trail is available
        $added = "{$this->year}-01-01 12:00:00";
        Carbon::setTestNow($added);
        $response = $this->json('POST', "person/$personId/schedule", ['slot_id' => $shift->id,]);
        $response->assertStatus(200);

        // Advance the clock
        $removed = "{$this->year}-01-02 13:00:00";
        Carbon::setTestNow($removed);
        $response = $this->json('DELETE', "person/{$personId}/schedule/{$shift->id}");
        $response->assertStatus(200);

        // Grab the audit trail
        $response = $this->json('GET', "person/$personId/schedule/log", ['year' => $this->year]);
        $response->assertStatus(200);

        $response->assertJson([
            'logs' => [
                [
                    'slot_id' => $shift->id,
                    'slot_description' => $shift->description,
                    'slot_begins' => (string)$shift->begins,
                    'added_at' => $added,
                    'person_added' => [
                        'id' => $personId,
                        'callsign' => $callsign,
                    ],
                    'removed_at' => $removed,
                    'person_removed' => [
                        'id' => $personId,
                        'callsign' => $callsign,
                    ],
                ]
            ]
        ]);
    }
}
