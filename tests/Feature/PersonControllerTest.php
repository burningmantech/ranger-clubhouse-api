<?php

namespace Tests\Feature;

use APp\Mail\AccountCreationMail;
use App\Mail\NotifyVCEmailChangeMail;
use App\Models\Person;
use App\Models\PersonLanguage;
use App\Models\PersonMentor;
use App\Models\PersonMessage;
use App\Models\PersonPosition;
use App\Models\PersonRole;
use App\Models\PersonSlot;
use App\Models\Position;
use App\Models\PositionCredit;
use App\Models\Role;
use App\Models\Slot;
use App\Models\Timesheet;
use App\Models\TraineeStatus;
use App\Models\TrainerStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PersonControllerTest extends TestCase
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
        $this->setting('VCEmail', 'vc@example.com');
        $this->setting('AccountCreationEmail', 'newuser@example.com');
    }


    /*
     * helper to update the person record
     */

    public function putPerson($person, $data)
    {
        return $this->json(
            'PUT',
            "person/{$person->id}",
            ['person' => $data]
        );
    }


    /*
     * Test a basic account cannot search
     */

    public function testNoSearchPrivileges()
    {
        $response = $this->json('GET', 'person', ['statuses' => 'active']);
        $response->assertStatus(403);
    }


    /**
     * Test searching for people
     *
     */


    public function testIndex()
    {
        $this->addRole(Role::ADMIN);

        $auditor = Person::factory()->create(['status' => 'auditor']);
        $prospective = Person::factory()->create(['status' => 'prospective']);

        $response = $this->actingAs($this->user)->json(
            'GET',
            'person',
            ['statuses' => 'auditor,prospective']
        );

        $response->assertStatus(200);
        $response->assertJsonStructure(['person']);
        $json = $response->json();

        // Should have two people
        $person = $json['person'];

        $this->assertCount(2, $person);

        // results should be the auditor and prospective
        $ids = [
            $auditor->id,
            $prospective->id,
        ];
        $this->assertContains($person[0]['id'], $ids);
        $this->assertContains($person[1]['id'], $ids);
        $this->assertNotEquals($person[0]['id'], $person[1]['id']);
    }


    /**
     * Test show another person record for an admin
     *
     */

    public function testShowForAdmin()
    {
        $this->addRole(Role::ADMIN);
        $person = Person::factory()->create();

        $response = $this->json('GET', "person/{$person->id}");
        $response->assertStatus(200);
        $response->assertJsonStructure(['person']);
    }


    /**
     * Test showing myself
     */

    public function testShowSelf()
    {
        $response = $this->json('GET', "person/{$this->user->id}");
        $response->assertStatus(200);
        $response->assertJsonStructure(['person']);
    }


    /**
     * Test fail for showing another record without roles
     */


    public function testShowOtherFail()
    {
        $person = Person::factory()->create();
        $response = $this->json('GET', "person/{$person->id}");
        $response->assertStatus(403);
    }

    /**
     * Test updating a person record for self.
     */

    public function testUpdatePersonBasic()
    {
        $newFirstName = 'Plan 9';
        $response = $this->putPerson($this->user, ['first_name' => $newFirstName]);

        $response->assertStatus(200);
        $this->assertDatabaseHas(
            'person',
            [
                'id' => $this->user->id,
                'first_name' => $newFirstName,
            ]
        );
    }


    /**
     * Test fail for trying to update the status field.
     */


    public function testFailStatusUpdate()
    {
        $personId = $this->user->id;
        $response = $this->putPerson($this->user, ['status' => 'auditor']);

        $response->assertStatus(200);
        $this->assertDatabaseHas(
            'person',
            [
                'id' => $personId,
                'status' => 'active',
            ]
        );
    }


    /**
     * Test success updating the status field
     */

    public function testStatusChangeSuccess()
    {
        $this->addRole(Role::ADMIN);
        $personId = $this->user->id;
        $response = $this->putPerson($this->user, ['status' => 'auditor']);

        $response->assertStatus(200);
        $this->assertDatabaseHas(
            'person',
            [
                'id' => $personId,
                'status' => 'auditor',
            ]
        );
    }


    /**
     * Test failure for trying to disapprove the callsign
     */

    /*
        Feb 2019 - Allowing callsign_approval to be changed at anytime.

        public function testChangeCallsignApprovalFailure()
        {
            $this->addRole(Role::ADMIN);
            $person = Person::factory()->create();

            $personId = $person->id;
            $response = $this->putPerson($person, [ 'callsign_approved' => false ]);

            $response->assertStatus(422);
            $this->assertDatabaseMissing(
                'person',
                [
                    'id'                => $personId,
                    'callsign_approved' => false,
                ]
            );

        }
    */

    /**
     * Test success on approving the callsign
     */


    public function testCallsignApprovalSuccess()
    {
        $this->addRole(Role::ADMIN);
        $person = Person::factory()->create(['status' => 'alpha', 'callsign_approved' => false]);

        $personId = $person->id;
        $response = $this->putPerson($person, ['callsign_approved' => true]);

        $response->assertStatus(200);
        $this->assertDatabaseHas(
            'person',
            [
                'id' => $personId,
                'callsign_approved' => true,
            ]
        );
    }

    /**
     * Test success on changing the status to past prospective
     */


    public function testPastProspectiveStatus()
    {
        $this->addRole(Role::ADMIN);
        $person = Person::factory()->create();

        $oldCallsign = $person->callsign;
        $personId = $person->id;
        $response = $this->putPerson($person, ['status' => Person::PAST_PROSPECTIVE]);

        $newCallsign = $person->last_name . substr($person->first_name, 0, 1) . (current_year() % 100);
        $response->assertStatus(200);
        $this->assertDatabaseHas(
            'person',
            [
                'id' => $personId,
                'callsign_approved' => false,
                'callsign' => $newCallsign,
                'formerly_known_as' => $oldCallsign
            ]
        );
    }

    /**
     * Test updating formerly_known_as when callsign is changed
     */

    public function testUpdateFormerlyKnownAsWhenCallsignChanges()
    {
        $this->addRole(Role::ADMIN);
        $person = Person::factory()->create();

        $oldCallsign = $person->callsign;
        $response = $this->putPerson($person, ['callsign' => 'Irregular Apocalypse']);
        $response->assertStatus(200);
        $person->refresh();
        $this->assertEquals($oldCallsign, $person->formerly_known_as);

        // change one more time to verify a comma was added..
        $response = $this->putPerson($person, ['callsign' => 'Zero Gravitas']);
        $response->assertStatus(200);
        $person->refresh();
        $this->assertEquals("$oldCallsign,Irregular Apocalypse", $person->formerly_known_as);
    }


    /**
     * Test emailing VCs when a prospective email addres change
     */

    public function testAlertVCAboutEmailChange()
    {
        $person = Person::factory()->create(['status' => Person::PROSPECTIVE]);
        $this->actingAs($person); // login

        Mail::fake();
        $response = $this->putPerson($person, ['email' => 'forkyourburn@shirtballs.badplace']);

        $response->assertStatus(200);

        Mail::assertQueued(NotifyVCEmailChangeMail::class, 1);
        Mail::assertQueued(NotifyVCEmailChangeMail::class, function ($mail) use ($person) {
            return $mail->person->id === $person->id;
        });
        /*        Mail::assertSent(
                    NotifyVCEmailChangeMail::class,
                    function ($mail) {
                        return $mail->hasTo(setting('VCEmail'));
                    }
                );*/
    }


    /**
     * Test deleting a person record
     */


    public function testPersonDelete()
    {
        $this->addRole(Role::ADMIN);
        $person = Person::factory()->create();

        $response = $this->json('DELETE', "person/{$person->id}");
        $response->assertStatus(204);
        $this->assertDatabaseMissing('person', ['id' => $person->id]);
    }


    /**
     * Test getting the year info
     */


    public function testYearInfo()
    {
        $response = $this->json('GET', "person/{$this->user->id}", [
            'year' => date('Y')
        ]);

        // TODO: add more factories & verify results
        $response->assertStatus(200);
    }


    /**
     * Test changing passwords
     */

    public function testChangePasswordSuccess()
    {
        $response = $this->json(
            'PATCH',
            "person/{$this->user->id}/password",
            [
                'password_old' => 'ineedashower!',
                'password' => 'abcdef',
                'password_confirmation' => 'abcdef',
            ]
        );

        $response->assertStatus(200);
    }


    /**
     * Test change password failure with invalid old password
     */

    public function testChangePasswordInvalidOldPasswordFailure()
    {
        $response = $this->json(
            'PATCH',
            "person/{$this->user->id}/password",
            [
                'password_old' => 'ineedashower! maybe',
                'password' => 'abcdef',
                'password_confirmation' => 'abcdef',
            ]
        );

        $response->assertStatus(422);
    }


    /**
     * Test changing password with admin user and no old password.
     */


    public function testChangePasswordWithAdminUserSuccess()
    {
        $this->addRole(Role::ADMIN);
        $response = $this->json(
            'PATCH',
            "person/{$this->user->id}/password",
            [
                'password' => 'abcdef',
                'password_confirmation' => 'abcdef',
            ]
        );

        $response->assertStatus(200);
    }

    /**
     * Test changing password with password reset token.
     */

    public function testChangePasswordWithResetTokenSuccess()
    {
        $token = $this->user->createTemporaryLoginToken();
        $response = $this->json(
            'PATCH',
            "person/{$this->user->id}/password",
            [
                'temp_token' => $token,
                'password' => 'abcdef',
                'password_confirmation' => 'abcdef',
            ]
        );
        $response->assertStatus(200);
    }

    /**
     * Test changing password with password reset token.
     */

    public function testChangePasswordWithInvalidResetToken()
    {
        $token = $this->user->createTemporaryLoginToken();
        $response = $this->json(
            'PATCH',
            "person/{$this->user->id}/password",
            [
                'temp_token' => $token . 'blah',
                'password' => 'abcdef',
                'password_confirmation' => 'abcdef',
            ]
        );
        $response->assertStatus(422);
    }


    /**
     * Test change password failure with password confirmation mismatch.
     */


    public function testChangePasswordMismatchConfirmationFailure()
    {
        $response = $this->json(
            'PATCH',
            "person/{$this->user->id}/password",
            [
                'password_old' => 'ineedashower!',
                'password' => 'abcdef',
                'password_confirmation' => 'abcdef12',
            ]
        );

        $response->assertStatus(422);
    }


    /**
     * Test retrieve person positions
     */

    public function testPersonPositionsSuccess()
    {
        for ($i = 0; $i < 3; $i++) {
            $position = Position::factory()->create();
            PersonPosition::factory()->create(
                [
                    'person_id' => $this->user->id,
                    'position_id' => $position->id,
                ]
            );
        }

        $response = $this->json('GET', "person/{$this->user->id}/positions");

        $response->assertStatus(200);
        $response->assertJsonStructure(['positions']);
        $json = $response->json();
        $this->assertCount(3, $json['positions']);
    }

    /**
     * Test retrieve person positions w/training
     */

    public function testPersonPositionsWithNoTrainingSuccess()
    {
        $personId = $this->user->id;

        Position::factory()->create(['id' => Position::DIRT, 'title' => 'Dirt']);

        PersonPosition::factory()->create([
            'person_id' => $personId,
            'position_id' => Position::DIRT
        ]);

        $response = $this->json('GET', "person/{$personId}/positions", [
            'include_training' => true,
            'year' => current_year()
        ]);

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'positions.*.id');
        $response->assertJson([
            'positions' => [[
                'id' => Position::DIRT,
                'is_untrained' => true
            ]]
        ]);
    }

    /**
     * Test retrieve person positions w/training
     */

    public function testPersonPositionsUnTrainedSuccess()
    {
        $personId = $this->user->id;

        Position::factory()->create(['id' => Position::DIRT, 'title' => 'Dirt']);
        PersonPosition::factory()->create([
            'person_id' => $personId,
            'position_id' => Position::DIRT
        ]);
        PersonPosition::factory()->create([
            'person_id' => $personId,
            'position_id' => Position::TRAINING
        ]);

        $slot = Slot::factory()->create([
            'begins' => date('Y-08-25 10:00:00'),
            'ends' => date('Y-08-25 11:00:00'),
            'position_id' => Position::TRAINING
        ]);

        PersonSlot::factory()->create([
            'person_id' => $personId,
            'slot_id' => $slot->id
        ]);

        TraineeStatus::factory()->create([
            'person_id' => $personId,
            'slot_id' => $slot->id,
            'passed' => true
        ]);

        /*
         * Setup a person to be a Green Dot Trainer who taught a session
         */

        Position::factory()->create([
            'id' => Position::DIRT_GREEN_DOT,
            'title' => 'Dirt - Green Dot',
            'training_position_id' => Position::GREEN_DOT_TRAINING,
        ]);

        Position::factory()->create([
            'id' => Position::GREEN_DOT_TRAINING,
            'title' => 'Green Dot Training',
        ]);

        Position::factory()->create([
            'id' => Position::GREEN_DOT_TRAINER,
            'title' => 'Green Dot Trainer',
        ]);

        PersonPosition::factory()->create([
            'person_id' => $personId,
            'position_id' => Position::DIRT_GREEN_DOT
        ]);

        PersonPosition::factory()->create([
            'person_id' => $personId,
            'position_id' => Position::GREEN_DOT_TRAINER
        ]);

        $trainerSlot = Slot::factory()->create([
            'begins' => date('Y-08-26 10:00:00'),
            'ends' => date('Y-08-26 11:00:00'),
            'position_id' => Position::GREEN_DOT_TRAINER
        ]);

        $traineeSlot = Slot::factory()->create([
            'begins' => date('Y-08-26 10:00:00'),
            'ends' => date('Y-08-26 11:00:00'),
            'position_id' => Position::GREEN_DOT_TRAINING
        ]);

        PersonSlot::factory()->create([
            'person_id' => $personId,
            'slot_id' => $trainerSlot->id
        ]);

        TrainerStatus::factory()->create([
            'person_id' => $personId,
            'slot_id' => $traineeSlot->id,
            'trainer_slot_id' => $trainerSlot->id,
            'status' => TrainerStatus::ATTENDED
        ]);

        $response = $this->json('GET', "person/{$personId}/positions", [
            'include_training' => true,
            'year' => current_year()
        ]);

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'positions.*.id');
        $json = $response->json();
        $this->assertFalse(isset($json['positions'][0]['is_untrained']));
        $this->assertFalse(isset($json['positions'][1]['is_untrained']));
    }


    /**
     * Test updating personing positions
     */


    public function testUpdatePositionsSuccess()
    {
        $this->addRole(Role::ADMIN);
        $personId = $this->user->id;

        $keepPosition = Position::factory()->create();
        PersonPosition::factory()->create(
            [
                'person_id' => $personId,
                'position_id' => $keepPosition->id,
            ]
        );

        $oldPosition = Position::factory()->create();
        PersonPosition::factory()->create(
            [
                'person_id' => $personId,
                'position_id' => $oldPosition->id,
            ]
        );

        $newPosition = Position::factory()->create();

        $response = $this->json(
            'POST',
            "person/$personId/positions",
            [
                'position_ids' => [
                    $keepPosition->id,
                    $newPosition->id,
                ],
                'team_manager_ids' => [],
            ]
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas(
            'person_position',
            [
                'person_id' => $personId,
                'position_id' => $keepPosition->id,
            ]
        );

        $this->assertDatabaseHas(
            'person_position',
            [
                'person_id' => $personId,
                'position_id' => $newPosition->id,
            ]
        );
        $this->assertDatabaseMissing(
            'person_position',
            [
                'person_id' => $personId,
                'position_id' => $oldPosition->id,
            ]
        );
    }


    /*
     * Test retrieving roles for a person
     */

    public function testRolesSuccess()
    {
        $personId = $this->user->id;

        $role = Role::factory()->create();
        $personRole = PersonRole::factory()->create(
            [
                'role_id' => $role->id,
                'person_id' => $personId,
            ]
        );

        $response = $this->json('GET', "person/$personId/roles");
        $response->assertStatus(200);

        $response->assertJson(
            [
                'roles' => [
                    [
                        'title' => $role->title,
                        'id' => $role->id,
                    ],
                ],
            ]
        );
    }


    /*
     * Test role updates for person
     */

    public function testUpdateRolesSuccess()
    {
        $this->addRole(Role::ADMIN);
        $personId = $this->user->id;

        $keepRole = Role::factory()->create();
        PersonRole::factory()->create(
            [
                'person_id' => $personId,
                'role_id' => $keepRole->id,
            ]
        );

        $oldRole = Role::factory()->create();
        PersonRole::factory()->create(
            [
                'person_id' => $personId,
                'role_id' => $oldRole->id,
            ]
        );

        $newRole = Role::factory()->create();

        $response = $this->json(
            'POST',
            "person/$personId/roles",
            [
                'role_ids' => [
                    $keepRole->id,
                    $newRole->id,
                ],
            ]
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas(
            'person_role',
            [
                'person_id' => $personId,
                'role_id' => $keepRole->id,
            ]
        );

        $this->assertDatabaseHas(
            'person_role',
            [
                'person_id' => $personId,
                'role_id' => $newRole->id,
            ]
        );

        $this->assertDatabaseMissing(
            'person_role',
            [
                'person_id' => $personId,
                'role_id' => $oldRole->id,
            ]
        );
    }

    /**
     * Ensure a non-Tech Ninja cannot add the Tech Ninja Role
     */
    public function test_user_cannot_add_tech_nina_role()
    {
        $person = Person::factory()->create();
        $this->addAdminRole();

        $response = $this->json('POST', "person/{$person->id}/roles", ['role_ids' => [Role::MENTOR, Role::TECH_NINJA]]);
        $response->assertStatus(200);
        $this->assertDatabaseMissing('person_role', [
            'person_id' => $person->id,
            'role_id' => Role::TECH_NINJA
        ]);

        $this->assertDatabaseHas('person_role', [
            'person_id' => $person->id,
            'role_id' => Role::MENTOR
        ]);
    }

    /**
     * Test if a tech ninja can add the tech ninja role to another person.
     *
     */
    public function test_user_can_add_tech_nina_role()
    {
        $person = Person::factory()->create();
        $this->addAdminRole();
        $this->addRole(Role::TECH_NINJA);

        $response = $this->json('POST', "person/{$person->id}/roles", ['role_ids' => [Role::MENTOR, Role::TECH_NINJA]]);
        $response->assertStatus(200);
        $this->assertDatabaseHas('person_role', [
            'person_id' => $person->id,
            'role_id' => Role::TECH_NINJA
        ]);

        $this->assertDatabaseHas('person_role', [
            'person_id' => $person->id,
            'role_id' => Role::MENTOR
        ]);
    }

    /*
     * Test how many years a person has rangered
     */

    public function testUserInfoYearsForActiveSucess()
    {
        $personId = $this->user->id;

        for ($i = 0; $i < 3; $i++) {
            $p = Timesheet::factory()->create(
                [
                    'person_id' => $personId,
                    'on_duty' => date("201$i-08-25 16:00:00"),
                    'off_duty' => date("201$i-08-25 18:00:00"),
                    'position_id' => Position::DIRT,
                ]
            );
        }

        $p = Timesheet::factory()->create([
            'person_id' => $personId,
            'position_id' => Position::ALPHA,
            'on_duty' => date("2009-08-25 16:00:00"),
            'off_duty' => date("2009-08-25 18:00:00"),
        ]);

        $response = $this->json('GET', "person/$personId/user-info");
        $response->status(200);
        $response->assertJson([
            'user_info' => [
                'years' => [2010, 2011, 2012],
                'all_years' => [2009, 2010, 2011, 2012]
            ]
        ]);
    }


    /*
     * Test person has no years
     */

    public function testUserInfoNoYearsSuccess()
    {
        $personId = $this->user->id;
        $response = $this->json('GET', "person/$personId/user-info");
        $response->status(200);
        $response->assertJson(['user_info' => ['years' => []]]);
    }


    /*
     * Test unread message count is returned correctly
     */

    public function testUnreadMessageCountSuccess()
    {
        for ($i = 0; $i < 3; $i++) {
            $m = PersonMessage::factory()->create(
                [
                    'recipient_callsign' => $this->user->callsign,
                ]
            );
        }

        $response = $this->json('GET', "person/{$this->user->id}/unread-message-count");
        $response->assertStatus(200);
        $response->assertJson(['unread_message_count' => 3]);
    }


    /*
     * Test teacher status for teacher
     */

    public function testUserInfoForTeacherSuccess()
    {
        $this->addRole([Role::TRAINER, Role::MENTOR, Role::ART_TRAINER]);
        PersonMentor::factory()->create(
            [
                'mentor_id' => $this->user->id,
                'person_id' => 1,
                'mentor_year' => date('Y'),
            ]
        );

        $response = $this->json('GET', "person/{$this->user->id}/user-info");

        $response->assertStatus(200);
        $response->assertJson(
            [
                'user_info' => [
                    'teacher' => [
                        'is_trainer' => true,
                        'is_mentor' => true,
                        'have_mentored' => true,
                    ]
                ]
            ]
        );
    }


    /*
     * Test teacher status for plain old student
     */

    public function testTeacherStatusForStudentSuccess()
    {
        $response = $this->json('GET', "person/{$this->user->id}/user-info");

        $response->assertStatus(200);
        $response->assertJson(
            [
                'user_info' => [
                    'teacher' => [
                        'is_trainer' => false,
                        'is_mentor' => false,
                        'have_mentored' => false,
                    ]
                ]
            ]
        );
    }


    /*
     * Test credit calucations for a year
     */

    public function testCreditsSuccess()
    {
        $personId = $this->user->id;
        $startTime = date('Y-m-d 10:00:00');
        $endTime = date('Y-m-d 12:00:00');
        $year = date('Y');

        $position = Position::factory()->create();
        PositionCredit::factory()->create(
            [
                'position_id' => $position->id,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'credits_per_hour' => 12.05,
            ]
        );

        Timesheet::factory()->create(
            [
                'person_id' => $personId,
                'on_duty' => $startTime,
                'off_duty' => $endTime,
                'position_id' => $position->id,
            ]
        );

        $response = $this->json('GET', "person/{$personId}/credits", ['year' => $year]);
        $response->assertStatus(200);
        $response->assertJson(['credits' => 24.10]);
    }


    /*
     * Test retrieving mentor's mentees
     */

    public function testMenteesSuccess()
    {
        $mentee = Person::factory()->create();
        $year = date('Y');
        PersonMentor::factory()->create(
            [
                'person_id' => $mentee->id,
                'mentor_id' => $this->user->id,
                'mentor_year' => $year,
                'status' => 'pass',
            ]
        );

        $response = $this->json('GET', "person/{$this->user->id}/mentees");
        $response->assertStatus(200);
        $this->assertCount(1, $response->json()['mentees'][0]['mentees']);
    }


    /*
     * Test retrieving a mentee's mentors
     */

    public function testMenteeMentorsSuccess()
    {
        $mentor = Person::factory()->create();
        $year = date('Y');
        PersonMentor::factory()->create(
            [
                'person_id' => $this->user->id,
                'mentor_id' => $mentor->id,
                'mentor_year' => $year,
                'status' => 'pass',
            ]
        );

        $response = $this->json('GET', "person/{$this->user->id}/mentors");
        $response->assertStatus(200);
        $response->assertJson(
            [
                'mentors' => [
                    [
                        'year' => (int)$year,
                        'status' => 'pass',
                        'mentors' => [[
                            'id' => $mentor->id,
                            'callsign' => $mentor->callsign,
                        ]]
                    ]
                ],
            ]
        );
    }


    private function buildRegisterData()
    {
        $faker = $this->faker;
        return [
            'email' => $faker->email,
            'password' => '12345',
            'first_name' => $faker->firstName,
            'last_name' => $faker->lastName,
            'street1' => substr($faker->address, 20),
            'city' => $faker->city,
            'state' => $faker->stateAbbr,
            'zip' => $faker->postcode,
            'country' => 'USA',
            'status' => 'auditor',
            'home_phone' => $faker->phoneNumber,

        ];
    }


    /*
     * Test registering a new account
     */

    public function testRegisterSuccess()
    {
        $faker = $this->faker;
        $data = [
            'intent' => 'Sitin',
            'person' => $this->buildRegisterData(),
        ];

        Mail::fake();

        $response = $this->json('POST', 'person/register', $data);
        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        $this->assertDatabaseHas(
            'person',
            [
                'email' => $data['person']['email'],
            ]
        );

        Mail::assertQueued(AccountCreationMail::class, 1);
        Mail::assertQueued(AccountCreationMail::class, function ($mail) use ($data) {
            return $mail->person->email === $data['person']['email'];
        });
    }


    /*
     * Test registration fail with non-auditor status
     */

    public function testRegisterStatusFailure()
    {
        $person = $this->buildRegisterData();
        $person['status'] = 'active';
        $data = [
            'intent' => 'Sitin',
            'person' => $person,
        ];

        Mail::fake();

        $response = $this->json('POST', 'person/register', $data);
        $response->assertStatus(422);

        Mail::assertNotQueued(AccountCreationMail::class);
    }

    /*
     * Test registration fail with non-auditor status
     */

    public function testRegisterDuplicateEmailFailure()
    {
        $person = $this->buildRegisterData();
        $person['email'] = $this->user->email;
        $data = [
            'intent' => 'Sitin',
            'person' => $person,
        ];

        Mail::fake();

        $response = $this->json('POST', 'person/register', $data);
        $response->assertStatus(422);
        $response->assertJson(['errors' => [
            [
                'source' => ['pointer' => '/data/attributes/email']
            ]
        ]]);

        Mail::assertQueued(AccountCreationMail::class, 1);
    }

    /*
     * Test People By Location
     */

    public function testPeopleByLocation()
    {
        $this->addRole(Role::ADMIN);

        $personUS = Person::factory()->create([
            'country' => 'US',
            'email' => $this->faker->email,
        ]);

        Timesheet::factory()->create([
            'person_id' => $personUS->id,
            'on_duty' => date('Y-m-d 10:00:00'),
            'off_duty' => date('Y-m-d 11:00:00'),
            'position_id' => Position::DIRT
        ]);

        $slot = Slot::factory()->create([
            'position_id' => Position::DIRT_GREEN_DOT,
            'begins' => date('Y-m-d 13:00:00'),
            'ends' => date('Y-m-d 14:00:00'),
            'max' => 10
        ]);

        PersonSlot::factory()->create([
            'person_id' => $personUS->id,
            'slot_id' => $slot->id,
        ]);

        $personCA = Person::factory()->create([
            'country' => 'CA',
            'email' => $this->faker->email,
        ]);

        $response = $this->json('GET', 'person/by-location', ['year' => date('Y')]);
        $response->assertStatus(200);

        $response->assertJson([
            'people' => [
                [
                    'id' => $personCA->id,
                    'email' => $personCA->email,
                    'callsign' => $personCA->callsign,
                    'status' => $personCA->status,
                    'city' => $personCA->city,
                    'state' => $personCA->state,
                    'country' => 'CA',
                    'worked' => 0,
                    'signed_up' => 0,
                ],
                [
                    'id' => $personUS->id,
                    'email' => $personUS->email,
                    'callsign' => $personUS->callsign,
                    'status' => $personUS->status,
                    'city' => $personUS->city,
                    'state' => $personUS->state,
                    'country' => 'US',
                    'worked' => 1,
                    'signed_up' => 1,
                ]
            ]
        ]);
    }

    /*
     * Test People By Location, but don't show email to people who can't see emails.
     */

    public function testPeopleByLocationNoEmail()
    {
        $this->addRole(Role::MANAGE);

        $personUS = Person::factory()->create([
            'country' => 'US',
            'email' => $this->faker->email,
        ]);

        Timesheet::factory()->create([
            'person_id' => $personUS->id,
            'on_duty' => date('Y-m-d 10:00:00'),
            'off_duty' => date('Y-m-d 11:00:00'),
            'position_id' => Position::DIRT
        ]);

        $slot = Slot::factory()->create([
            'position_id' => Position::DIRT_GREEN_DOT,
            'begins' => date('Y-m-d 13:00:00'),
            'ends' => date('Y-m-d 14:00:00'),
            'max' => 10
        ]);

        PersonSlot::factory()->create([
            'person_id' => $personUS->id,
            'slot_id' => $slot->id,
        ]);

        $personCA = Person::factory()->create([
            'country' => 'CA',
            'email' => $this->faker->email,
        ]);

        $response = $this->json('GET', 'person/by-location', ['year' => date('Y')]);
        $response->assertStatus(200);

        $response->assertJson([
            'people' => [
                [
                    'id' => $personCA->id,
                    'callsign' => $personCA->callsign,
                    'status' => $personCA->status,
                    'city' => $personCA->city,
                    'state' => $personCA->state,
                    'country' => 'CA',
                    'worked' => 0,
                    'signed_up' => 0,
                ],
                [
                    'id' => $personUS->id,
                    'callsign' => $personUS->callsign,
                    'status' => $personUS->status,
                    'city' => $personUS->city,
                    'state' => $personUS->state,
                    'country' => 'US',
                    'worked' => 1,
                    'signed_up' => 1,
                ]
            ]
        ]);

        $response->assertJsonMissing([
            'people' => [
                [ 'email' => $personCA->email ],
                [ 'email' => $personUS->email ]
            ]
        ]);
    }

    /*
     * Test People By Role
     */

    public function testPeopleByRole()
    {
        // New roles may have seeder migrations, and we don't want that here.
        DB::table('role')->delete();

        $this->addRole(Role::MANAGE);

        $adminRole = Role::factory()->create([
            'id' => Role::ADMIN,
            'title' => 'Admin',
        ]);

        $manageRole = Role::factory()->create([
            'id' => Role::MANAGE,
            'title' => 'Manage'
        ]);

        $adminPerson = Person::factory()->create();

        PersonRole::factory()->create([
            'person_id' => $adminPerson->id,
            'role_id' => Role::ADMIN
        ]);

        $response = $this->json('GET', 'role/people-by-role');
        $response->assertStatus(200);

        $response->assertJson([
            'roles' => [
                [
                    'id' => Role::ADMIN,
                    'title' => 'Admin',
                    'people' => [
                        [
                            'id' => $adminPerson->id,
                            'callsign' => $adminPerson->callsign
                        ]
                    ]
                ],
                [
                    'id' => Role::MANAGE,
                    'title' => 'Manage',
                    'people' => [
                        [
                            'id' => $this->user->id,
                            'callsign' => $this->user->callsign
                        ]
                    ]
                ]
            ]
        ]);
    }

    /*
     * Test People By Role
     */

    public function testPeopleByStatus()
    {
        $this->addRole(Role::MANAGE);

        $deceased = Person::factory()->create(['status' => 'deceased']);
        $inactive = Person::factory()->create(['status' => 'inactive']);
        $response = $this->json('GET', 'person/by-status');
        $response->assertStatus(200);

        $response->assertJson([
            'statuses' => [
                [
                    'status' => 'active',
                    'people' => [
                        [
                            'id' => $this->user->id,
                            'callsign' => $this->user->callsign,
                        ]
                    ]
                ],
                [
                    'status' => 'deceased',
                    'people' => [
                        [
                            'id' => $deceased->id,
                            'callsign' => $deceased->callsign,
                        ]
                    ]
                ],
                [
                    'status' => 'inactive',
                    'people' => [
                        [
                            'id' => $inactive->id,
                            'callsign' => $inactive->callsign,
                        ]
                    ]
                ],
            ]
        ]);
    }

     /*
     * People By Status Change Report
     */

    public function testPeopleByStatusChangeReport()
    {
        $this->addRole(Role::ADMIN);
        $year = 2016;

        // Create a current timesheet so test account does not appear in any list.
        Timesheet::factory()->create([
            'person_id' => $this->user->id,
            'position_id' => Position::DIRT,
            'on_duty' => date("$year-01-01 10:00:00"),
            'off_duty' => date("$year-01-01 11:00:00")
        ]);

        // Inactive recommendation - an active account who has not worked in the last 3 years but may have worked in the last 5.
        $inactive = Person::factory()->create(['status' => Person::ACTIVE]);

        $inactiveYear = $year - 3;
        Timesheet::factory()->create([
            'person_id' => $inactive->id,
            'position_id' => Position::DIRT,
            'on_duty' => date("$inactiveYear-09-01 10:00:00"),
            'off_duty' => date("$inactiveYear-09-01 11:00:00"),
        ]);

        // Retirement recommendation - an inactive account who has not worked in the last 5 years.
        $retired = Person::factory()->create(['status' => Person::INACTIVE]);

        $retiredYear = $year - 6;
        Timesheet::factory()->create([
            'person_id' => $retired->id,
            'position_id' => Position::DIRT,
            'on_duty' => date("$retiredYear-09-01 10:00:00"),
            'off_duty' => date("$retiredYear-09-01 11:00:00"),
        ]);

        // Active recommendation - an inactive account that worked this last year.
        $active = Person::factory()->create(['status' => Person::INACTIVE]);

        Timesheet::factory()->create([
            'person_id' => $active->id,
            'position_id' => Position::DIRT,
            'on_duty' => date('2016-09-01 10:00:00'),
            'off_duty' => date('2016-09-01 11:00:00'),
        ]);

        // Past Prospective recommendation - any bonked, alpha, prospective account
        $pastProspective = Person::factory()->create(['status' => 'prospective']);

        $vintage = Person::factory()->create();
        for ($workYear = $year - 10; $workYear <= $year; $workYear++) {
            Timesheet::factory()->create([
                'person_id' => $vintage->id,
                'position_id' => Position::DIRT,
                'on_duty' => date("$workYear-01-01 10:00:00"),
                'off_duty' => date("$workYear-01-01 11:00:00")
            ]);
        }

        // Mark callsign as vintage - any active who has worked for
        $response = $this->json('GET', 'person/by-status-change', ['year' => $year]);

        $response->assertJson([
            'inactives' => [['id' => $inactive->id, 'last_year' => $inactiveYear]],
            'retired' => [['id' => $retired->id, 'last_year' => $retiredYear]],
            'actives' => [['id' => $active->id, 'last_year' => $year]],
            'past_prospectives' => [['id' => $pastProspective->id]],
            'vintage' => [['id' => $vintage->id]]
        ]);
    }

    /*
     * Bulk Callsign/Email Lookup
     */

    public function testBulkLookup()
    {
        $this->addAdminRole();
        $existsCallsign = Person::factory()->create();
        $existsEmail = Person::factory()->create();

        $response = $this->json('POST', 'person/bulk-lookup', [
            'people' => [
                $existsEmail->email,
                $existsCallsign->callsign,
                'does not exists'
            ]
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'people' => [
                ['result' => 'success', 'id' => $existsEmail->id, 'callsign' => $existsEmail->callsign],
                ['result' => 'success', 'id' => $existsCallsign->id, 'callsign' => $existsCallsign->callsign],
                ['result' => 'not-found', 'person' => 'does not exists']
            ]
        ]);
    }
}
