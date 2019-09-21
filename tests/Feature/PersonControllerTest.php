<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

use App\Models\Person;
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

use App\Mail\NotifyVCEmailChangeMail;
use APp\Mail\AccountCreationMail;
use APp\Mail\WelcomeMail;

class PersonControllerTest extends TestCase
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
        $response = $this->json('GET', 'person', [ 'statuses' => 'active' ]);
        $response->assertStatus(403);

    }


    /**
     * Test searching for people
     *
     */


    public function testIndex()
    {
        $this->addRole(Role::ADMIN);

        $auditor     = factory(Person::class)->create([ 'status' => 'auditor' ]);
        $prospective = factory(Person::class)->create([ 'status' => 'prospective' ]);

        $response = $this->actingAs($this->user)->json(
            'GET',
            'person',
            [ 'statuses' => 'auditor,prospective' ]
        );

        $response->assertStatus(200);
        $response->assertJsonStructure([ 'person' ]);
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
        $person = factory(Person::class)->create();

        $response = $this->json('GET', "person/{$person->id}");
        $response->assertStatus(200);
        $response->assertJsonStructure([ 'person' ]);

    }


    /**
     * Test showing myself
     */

    public function testShowSelf()
    {
        $response = $this->json('GET', "person/{$this->user->id}");
        $response->assertStatus(200);
        $response->assertJsonStructure([ 'person' ]);

    }


    /**
     * Test fail for showing another record without roles
     */


    public function testShowOtherFail()
    {
        $person   = factory(Person::class)->create();
        $response = $this->json('GET', "person/{$person->id}");
        $response->assertStatus(403);

    }

    /**
     * Test updating a person record for self.
     */

    public function testUpdatePersonBasic()
    {
        $newFirstName = 'Plan 9';
        $response     = $this->putPerson($this->user, [ 'first_name' => $newFirstName ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas(
            'person',
            [
                'id'         => $this->user->id,
                'first_name' => $newFirstName,
            ]
        );

    }


    /**
     * Test updating the person language fields
     */

    public function testUpdatePersonLanguage()
    {
        $personId = $this->user->id;
        $response = $this->putPerson($this->user, [ 'languages' => 'sumarian,19th century victorian burner' ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas(
            'person_language',
            [
                'person_id'     => $personId,
                'language_name' => 'sumarian',
            ]
        );
        $this->assertDatabaseHas(
            'person_language',
            [
                'person_id'     => $personId,
                'language_name' => '19th century victorian burner',
            ]
        );

    }


    /**
     * Test fail for trying to update the status field.
     */


    public function testFailStatusUpdate()
    {
        $personId = $this->user->id;
        $response = $this->putPerson($this->user, [ 'status' => 'auditor' ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas(
            'person',
            [
                'id'     => $personId,
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
        $response = $this->putPerson($this->user, [ 'status' => 'auditor' ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas(
            'person',
            [
                'id'     => $personId,
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
        $person = factory(Person::class)->create();

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
        $person = factory(Person::class)->create([ 'status' => 'alpha', 'callsign_approved' => false ]);

        $personId = $person->id;
        $response = $this->putPerson($person, [ 'callsign_approved' => true ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas(
            'person',
            [
                'id'                => $personId,
                'callsign_approved' => true,
            ]
        );

    }

    /**
     * Test updating formerly_known_as when callsign is changed
     */

     public function testUpdateFormerlyKnownAsWhenCallsignChanges()
     {
         $this->addRole(Role::ADMIN);
         $person = factory(Person::class)->create();

         $oldCallsign = $person->callsign;
         $response = $this->putPerson($person, [ 'callsign' => 'Irregular Apocalypse']);
         $response->assertStatus(200);
         $person->refresh();
         $this->assertEquals($oldCallsign, $person->formerly_known_as);

         // change one more time to verify a comma was added..
         $response = $this->putPerson($person, [ 'callsign' => 'Zero Gravitas']);
         $response->assertStatus(200);
         $person->refresh();
         $this->assertEquals("$oldCallsign,Irregular Apocalypse", $person->formerly_known_as);
     }


    /**
     * Test emailing VCs when a prospective email addres change
     */

    public function testAlertVCAboutEmailChange()
    {
        $this->user->status = 'prospective';
        $this->user->save();
        $personId = $this->user->id;

        Mail::fake();

        $response = $this->putPerson($this->user, [ 'email' => 'forkyourburn@shirtballs.badplace' ]);

        $response->assertStatus(200);

        Mail::assertSent(
            NotifyVCEmailChangeMail::class,
            function ($mail) {
                return $mail->hasTo(setting('VCEmail'));
            }
        );

    }


    /**
     * Test deleting a person record
     */


    public function testPersonDelete()
    {
        $this->addRole(Role::ADMIN);
        $person = factory(Person::class)->create();

        $response = $this->json('DELETE', "person/{$person->id}");
        $response->assertStatus(204);
        $this->assertDatabaseMissing('person', [ 'id' => $person->id]);

    }


    /**
     * Test getting the year info
     */


    public function testYearInfo()
    {
        $response = $this->json('GET', "person/{$this->user->id}", [
            'year'  => date('Y')
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
                'password_old'          => 'ineedashower!',
                'password'              => 'abcdef',
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
                'password_old'          => 'ineedashower! maybe',
                'password'              => 'abcdef',
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
                'password'              => 'abcdef',
                'password_confirmation' => 'abcdef',
            ]
        );

        $response->assertStatus(200);

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
                'password_old'          => 'ineedashower!',
                'password'              => 'abcdef',
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
            $position = factory(Position::class)->create();
            factory(PersonPosition::class)->create(
                [
                    'person_id'   => $this->user->id,
                    'position_id' => $position->id,
                ]
            );
        }

        $response = $this->json('GET', "person/{$this->user->id}/positions");

        $response->assertStatus(200);
        $response->assertJsonStructure([ 'positions' ]);
        $json = $response->json();
        $this->assertCount(3, $json['positions']);

    }


    /**
     * Test updating personing positions
     */


    public function testUpdatePositionsSuccess()
    {
        $this->addRole(Role::ADMIN);
        $personId = $this->user->id;

        $keepPosition = factory(Position::class)->create();
        factory(PersonPosition::class)->create(
            [
                'person_id'   => $personId,
                'position_id' => $keepPosition->id,
            ]
        );

        $oldPosition = factory(Position::class)->create();
        factory(PersonPosition::class)->create(
            [
                'person_id'   => $personId,
                'position_id' => $oldPosition->id,
            ]
        );

        $newPosition = factory(Position::class)->create();

        $response = $this->json(
            'POST',
            "person/$personId/positions",
            [
                'position_ids' => [
                    $keepPosition->id,
                    $newPosition->id,
                ],
            ]
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas(
            'person_position',
            [
                'person_id'   => $personId,
                'position_id' => $keepPosition->id,
            ]
        );

        $this->assertDatabaseHas(
            'person_position',
            [
                'person_id'   => $personId,
                'position_id' => $newPosition->id,
            ]
        );
        $this->assertDatabaseMissing(
            'person_position',
            [
                'person_id'   => $personId,
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

        $role       = factory(Role::class)->create();
        $personRole = factory(PersonRole::class)->create(
            [
                'role_id'   => $role->id,
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
                        'id'    => $role->id,
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

        $keepRole = factory(Role::class)->create();
        factory(PersonRole::class)->create(
            [
                'person_id' => $personId,
                'role_id'   => $keepRole->id,
            ]
        );

        $oldRole = factory(Role::class)->create();
        factory(PersonRole::class)->create(
            [
                'person_id' => $personId,
                'role_id'   => $oldRole->id,
            ]
        );

        $newRole = factory(Role::class)->create();

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
                'role_id'   => $keepRole->id,
            ]
        );

        $this->assertDatabaseHas(
            'person_role',
            [
                'person_id' => $personId,
                'role_id'   => $newRole->id,
            ]
        );

        $this->assertDatabaseMissing(
            'person_role',
            [
                'person_id' => $personId,
                'role_id'   => $oldRole->id,
            ]
        );

    }


    /*
     * Test how many years a person has rangered
     */

    public function testUserInfoYearsForActiveSucess()
    {
        $personId = $this->user->id;

        for ($i = 0; $i < 3; $i++) {
            $p = factory(Timesheet::class)->create(
                [
                    'person_id' => $personId,
                    'on_duty'   => date("201$i-08-25 16:00:00"),
                    'off_duty'  => date("201$i-08-25 18:00:00"),
                    'position_id' => Position::DIRT,
                ]
            );
        }

        $p = factory(Timesheet::class)->create([
            'person_id' => $personId,
            'position_id' => Position::ALPHA,
            'on_duty'   => date("2009-08-25 16:00:00"),
            'off_duty'  => date("2009-08-25 18:00:00"),
        ]);

        $response = $this->json('GET', "person/$personId/user-info");
        $response->status(200);
        $response->assertJson([
            'user_info' => [
                'years' => [ 2010, 2011, 2012 ],
                'all_years' => [ 2009, 2010, 2011, 2012 ]
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
        $response->assertJson([ 'user_info' => [ 'years' => [  ]]]);
    }


    /*
     * Test unread message count is returned correctly
     */

    public function testUnreadMessageCountSuccess()
    {
        for ($i = 0; $i < 3; $i++) {
            factory(PersonMessage::class)->create(
                [
                    'recipient_callsign' => $this->user->callsign,
                ]
            );
        }

        $response = $this->json('GET', "person/{$this->user->id}/unread-message-count");
        $response->assertStatus(200);
        $response->assertJson([ 'unread_message_count' => 3]);

    }


    /*
     * Test teacher status for teacher
     */

    public function testUserInfoForTeacherSuccess()
    {
        $this->addRole([ Role::TRAINER, Role::MENTOR, Role::ART_TRAINER]);
        factory(PersonMentor::class)->create(
            [
                'mentor_id'   => $this->user->id,
                'person_id'   => 1,
                'mentor_year' => date('Y'),
            ]
        );

        $response = $this->json('GET', "person/{$this->user->id}/user-info");

        $response->assertStatus(200);
        $response->assertJson(
            [
                'user_info' => [
                    'teacher' => [
                        'is_trainer'    => true,
                        'is_mentor'     => true,
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
                        'is_trainer'    => false,
                        'is_mentor'     => false,
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
        $personId  = $this->user->id;
        $startTime = date('Y-m-d 10:00:00');
        $endTime   = date('Y-m-d 12:00:00');
        $year      = date('Y');

        $position = factory(Position::class)->create();
        factory(PositionCredit::class)->create(
            [
                'position_id'      => $position->id,
                'start_time'       => $startTime,
                'end_time'         => $endTime,
                'credits_per_hour' => 12.05,
            ]
        );

        factory(Timesheet::class)->create(
            [
                'person_id'   => $personId,
                'on_duty'     => $startTime,
                'off_duty'    => $endTime,
                'position_id' => $position->id,
            ]
        );

        $response = $this->json('GET', "person/{$personId}/credits", [ 'year' => $year ]);
        $response->assertStatus(200);
        $response->assertJson([ 'credits' => 24.10 ]);

    }


    /*
     * Test retrieving mentor's mentees
     */

    public function testMenteesSuccess()
    {
        $mentee = factory(Person::class)->create();
        $year   = date('Y');
        factory(PersonMentor::class)->create(
            [
                'person_id'   => $mentee->id,
                'mentor_id'   => $this->user->id,
                'mentor_year' => $year,
                'status'      => 'pass',
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
        $mentor = factory(Person::class)->create();
        $year   = date('Y');
        factory(PersonMentor::class)->create(
            [
                'person_id'   => $this->user->id,
                'mentor_id'   => $mentor->id,
                'mentor_year' => $year,
                'status'      => 'pass',
            ]
        );

        $response = $this->json('GET', "person/{$this->user->id}/mentors");
        $response->assertStatus(200);
        $response->assertJson(
            [
                'mentors' => [
                    [
                        'year'      => (int) $year,
                        'status'    => 'pass',
                        'mentors' => [[
                            'id' => $mentor->id,
                            'callsign'  => $mentor->callsign,
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
            'email'      => $faker->email,
            'password'   => '12345',
            'first_name' => $faker->firstName,
            'last_name'  => $faker->lastName,
            'street1'    => substr($faker->address, 20),
            'city'       => $faker->city,
            'state'      => $faker->stateAbbr,
            'zip'        => $faker->postcode,
            'country'    => 'USA',
            'status'     => 'auditor',
            'home_phone' => $faker->phoneNumber,

        ];

    }


    /*
     * Test registering a new account
     */

    public function testRegisterSuccess()
    {
        $faker = $this->faker;
        $data  = [
            'intent' => 'Sitin',
            'person' => $this->buildRegisterData(),
        ];

        Mail::fake();

        $response = $this->json('POST', 'person/register', $data);
        $response->assertStatus(200);
        $response->assertJson([ 'status' => 'success' ]);

        $this->assertDatabaseHas(
            'person',
            [
                'email' => $data['person']['email'],
            ]
        );

        Mail::assertSent(
            AccountCreationMail::class,
            function ($mail) {
                return $mail->hasTo(setting('AccountCreationEmail'));
            }
        );

        Mail::assertNotSent(WelcomeMail::class);

    }


    /*
     * Test registration fail with non-auditor status
     */

    public function testRegisterStatusFailure()
    {
        $person           = $this->buildRegisterData();
        $person['status'] = 'active';
        $data             = [
            'intent' => 'Sitin',
            'person' => $person,
        ];

        Mail::fake();

        $response = $this->json('POST', 'person/register', $data);
        $response->assertStatus(422);

        Mail::assertNothingSent();

    }

    /*
     * Test registration fail with non-auditor status
     */

    public function testRegisterDuplicateEmailFailure()
    {
        $person          = $this->buildRegisterData();
        $person['email'] = $this->user->email;
        $data            = [
            'intent' => 'Sitin',
            'person' => $person,
        ];

        Mail::fake();

        $response = $this->json('POST', 'person/register', $data);
        $response->assertStatus(200);
        $response->assertJson([ 'status' => 'email-exists']);

        Mail::assertSent(
            AccountCreationMail::class,
            function ($mail) {
                return $mail->hasTo(setting('AccountCreationEmail'));
            }
        );

    }

    /*
     * Test People By Location
     */

    public function testPeopleByLocation()
    {
        $this->addRole(Role::ADMIN);

        $personUS = factory(Person::class)->create([
            'country' => 'US'
        ]);

        factory(Timesheet::class)->create([
            'person_id'   => $personUS->id,
            'on_duty'     => date('Y-m-d 10:00:00'),
            'off_duty'    => date('Y-m-d 11:00:00'),
            'position_id' => Position::DIRT
        ]);

        $slot = factory(Slot::class)->create([
            'position_id'   => Position::DIRT_GREEN_DOT,
            'begins'        => date('Y-m-d 13:00:00'),
            'ends'          => date('Y-m-d 14:00:00'),
            'max'           => 10
        ]);

        factory(PersonSlot::class)->create([
            'person_id' => $personUS->id,
            'slot_id'   => $slot->id,
        ]);

        $personCA = factory(Person::class)->create([
            'country' => 'CA'
        ]);

        $response = $this->json('GET', 'person/by-location', [ 'year' => date('Y' )]);
        $response->assertStatus(200);

        $response->assertJson([
            'people'    => [
                [
                    'id'        => $personCA->id,
                    'callsign'  => $personCA->callsign,
                    'status'    => $personCA->status,
                    'city'      => $personCA->city,
                    'state'     => $personCA->state,
                    'country'   => 'CA',
                    'worked'    => 0,
                    'signed_up' => 0,
                ],
                [
                    'id'        => $personUS->id,
                    'callsign'  => $personUS->callsign,
                    'status'    => $personUS->status,
                    'city'      => $personUS->city,
                    'state'     => $personUS->state,
                    'country'   => 'US',
                    'worked'    => 1,
                    'signed_up' => 1,
                ]
            ]
        ]);
    }

    /*
     * Test People By Role
     */

    public function testPeopleByRole()
    {
        $this->addRole(Role::MANAGE);

        $adminRole = factory(Role::class)->create([
            'id' => Role::ADMIN,
            'title' => 'Admin',
        ]);

        $manageRole = factory(Role::class)->create([
            'id'    => Role::MANAGE,
            'title' => 'Manage'
        ]);

        $adminPerson = factory(Person::class)->create();

        factory(PersonRole::class)->create([
            'person_id' => $adminPerson->id,
            'role_id'   => Role::ADMIN
        ]);

        $response = $this->json('GET', 'person/by-role');
        $response->assertStatus(200);

        $response->assertJson([
            'roles' => [
                [
                    'id'    => Role::ADMIN,
                    'title' => 'Admin',
                    'people' => [
                        [
                            'id' => $adminPerson->id,
                            'callsign' => $adminPerson->callsign
                        ]
                    ]
                ],
                [
                    'id'    => Role::MANAGE,
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

}
