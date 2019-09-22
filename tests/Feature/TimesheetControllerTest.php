<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use App\Models\Person;
use App\Models\PersonPosition;
use App\Models\PersonSlot;
use App\Models\Position;
use App\Models\PositionCredit;
use App\Models\Role;
use App\Models\Slot;
use App\Models\Timesheet;
use App\Models\TimesheetLog;
use App\Models\TimesheetMissing;
use App\Models\TraineeStatus;

use App\Helpers\SqlHelper;

class TimesheetControllerTest extends TestCase
{
    use WithFaker;
    use RefreshDatabase;

    /*
     * have each test have a fresh user that is logged in,
     */

    public function setUp() : void
    {
        parent::setUp();

        $this->signInUser();
        $this->addRole(Role::MANAGE);

        $year = $this->year = date('Y');

        // Setup default (real world) positions
        factory(Position::class)->create(
            [
                'id'    => Position::DIRT,
                'title' => 'Dirt',
                'type'  => 'Frontline',
            ]
        );

        factory(Position::class)->create(
            [
                'id'    => Position::DIRT_TRAINING,
                'title' => 'Training',
                'type'  => 'Training',
            ]
        );

        // Setup default (real world) positions
        $this->hqWindow = factory(Position::class)->create(
            [
                'id'    => Position::HQ_WINDOW,
                'title' => 'HQ Window',
                'type'  => 'Frontline',
            ]
        );

        factory(PositionCredit::class)->create([
            'position_id'   => Position::DIRT,
            'start_time'    => date("$year-08-15 00:00:00"),
            'end_time'      => date("$year-09-02 00:00:00"),
            'credits_per_hour'  => 2.00,
            'description'   => 'All Dirt All The Time',
        ]);

        $this->targetPerson = factory(Person::class)->create();

        factory(PersonPosition::class)->create([
            'person_id' => $this->targetPerson->id,
            'position_id'   => Position::DIRT,
        ]);

        $onDuty = date("$year-08-25 06:00:00");
        $offDuty = date("$year-08-25 12:00:00");

        $this->timesheet = factory(Timesheet::class)->create([
            'person_id' => $this->targetPerson->id,
            'on_duty'   => $onDuty,
            'off_duty'  => $offDuty,
            'position_id'   => Position::DIRT,
        ]);

        factory(TimesheetLog::class)->create([
            'person_id'        => $this->targetPerson->id,
            'timesheet_id'     => $this->timesheet->id,
            'create_person_id' => $this->user->id,
            'action'           => 'signon',
            'created_at'       => $onDuty,
            'message'          => 'Dirt '.$onDuty
        ]);

        factory(TimesheetLog::class)->create([
            'person_id'        => $this->targetPerson->id,
            'timesheet_id'     => $this->timesheet->id,
            'create_person_id' => $this->user->id,
            'action'           => 'signoff',
            'created_at'       => $offDuty,
            'message'          => 'Dirt '.$offDuty
        ]);
    }

    /*
     * Create a training session, student record, and either pass or fail the person.
     */

    public function createTrainingSession($passed)
    {
        $slot = factory(Slot::class)->create([
            'begins'    => date("Y-01-01 00:00:00"),
            'ends'      => date('Y-01-01 01:00:00'),
            'position_id'  => Position::DIRT_TRAINING,
        ]);

        factory(PersonSlot::class)->create([
            'person_id' => $this->targetPerson->id,
            'slot_id'   => $slot->id
        ]);

        factory(TraineeStatus::class)->create([
            'person_id'   => $this->targetPerson->id,
            'slot_id'     => $slot->id,
            'passed'      => $passed
        ]);
    }

    /*
     * Obtain  timesheets for person
     */

    public function testIndexForPerson()
    {
        $response = $this->json('GET', 'timesheet', [ 'year' => $this->year, 'person_id' => $this->targetPerson->id ]);
        $response->assertStatus(200);
        $this->assertCount(1, $response->json()['timesheet']);
        $response->assertJson([
            'timesheet' => [ [ 'credits'   => 12.00 ] ]
        ]);
    }

    /*
     * Fail to find  timesheets for person
     */

    public function testIndexNoneFound()
    {
        $response = $this->json('GET', 'timesheet', [ 'year' => $this->year - 1, 'person_id' => $this->targetPerson->id ]);
        $response->assertStatus(200);
        $this->assertCount(0, $response->json()['timesheet']);
    }

    /*
     * Retrieve the log for a person
     */

    public function testTimesheetLog()
    {
        $year = $this->year;
        $onDuty = date("$year-08-25 06:00:00");

        $response = $this->json('GET', 'timesheet/log', [ 'year' => $this->year, 'person_id' => $this->targetPerson->id ]);
        $response->assertStatus(200);
        $this->assertCount(1, $response->json()['logs']);
        $this->assertCount(2, $response->json()['logs'][0]['logs']);
        $response->assertJson([
             'logs' => [
                 [
                  'timesheet_id' => $this->timesheet->id,
                  'logs' => [ [
                      'creator_person_id' => $this->user->id,
                      'creator_callsign' => $this->user->callsign,
                      'created_at'  => date("$year-08-25 06:00:00"),
                      'action'  => 'signon',
                      'message' => 'Dirt '.$onDuty,
                    ]
                ] ],
             ]
         ]);
    }

    /*
     * Test creating a timesheet (raw)
     */

    public function testTimesheetStore()
    {
        $this->addRole(Role::ADMIN);

        $year = $this->year;
        $data = [
             'person_id'    => $this->targetPerson->id,
             'position_id'  => Position::DIRT_GREEN_DOT,
             'on_duty'      => date("$year-09-02 03:00:00"),
             'off_duty'      => date("$year-09-02 06:00:00"),
         ];


        $response = $this->json('POST', 'timesheet', [ 'timesheet' => $data ]);
        $response->assertStatus(200);
        $this->assertDatabaseHas('timesheet', $data);
    }

    /*
     * Update a timesheet
     */

    public function testTimesheetUpdate()
    {
        $timesheetId = $this->timesheet->id;
        $oldOnDuty = $this->timesheet->on_duty;
        $oldOffDuty = $this->timesheet->off_duty;

        $this->addRole(Role::ADMIN);

        $year = $this->year;
        $onDuty = date("$year-09-10 00:00:00");
        $offDuty = date("$year-09-10 13:00:00");

        $data = [
              'position_id' => Position::HQ_WINDOW,
              'on_duty'    => $onDuty,
              'off_duty'   => $offDuty
          ];

        $response = $this->json('PUT', "timesheet/{$timesheetId}", [
              'timesheet' => $data
          ]);

        $response->assertStatus(200);
        $data['id'] = $timesheetId;
        $this->assertDatabaseHas('timesheet', $data);

        $this->assertDatabaseHas('timesheet_log', [
              'timesheet_id'    => $timesheetId,
              'action'          => 'update',
              'create_person_id' => $this->user->id,
              'message'         => "on duty old 2019-08-25 06:00:00 new 2019-09-10 00:00:00, off duty old 2019-08-25 12:00:00 new 2019-09-10 13:00:00, position old Dirt new HQ Window",
          ]);
    }

    /*
     * Timesheet is prevented from being updated without the right permissions.
     */

    public function testTimesheetUpdateNotAdmin()
    {
        $timesheetId = $this->timesheet->id;

        $response = $this->json('PUT', "timesheet/{$timesheetId}", [
               'timesheet' => [
                   'position_id' => Position::HQ_WINDOW,
                ]
           ]);
        $response->assertStatus(200);
        $this->assertDatabaseHas('timesheet', [
               'id'  => $timesheetId,
               'position_id' => Position::DIRT
           ]);
    }

    /*
     * Delete a timesheet
     */

    public function testTimesheetDestroy()
    {
        $this->addRole(Role::ADMIN);
        $timesheetId = $this->timesheet->id;

        $response = $this->json('DELETE', "timesheet/{$timesheetId}");
        $response->assertStatus(204);
        $this->assertDatabaseMissing('timesheet', [ 'id'  => $timesheetId ]);
    }

    /*
     * Sign in a person who is trained
     */

    public function testSigninForTrained()
    {
        $this->createTrainingSession(true);
        $targetPersonId = $this->targetPerson->id;

        $response = $this->json('POST', 'timesheet/signin', [
            'person_id'     => $targetPersonId,
            'position_id'   => Position::DIRT,
        ]);
        $response->assertStatus(200);
        $response->assertJson([
            'status'       => 'success',
            'forced'       => false
        ]);

        $timesheetId = $response->json('timesheet_id');

        $this->assertDatabaseHas('timesheet_log', [
            'timesheet_id'  => $timesheetId,
            'action'        => 'signon'
        ]);
    }

    /*
     * Prevent a sign in for an untrained person
     */

    public function testNoSignInForUntrainedPerson()
    {
        $this->createTrainingSession(false);
        $targetPersonId = $this->targetPerson->id;

        $response = $this->json('POST', 'timesheet/signin', [
            'person_id'     => $targetPersonId,
            'position_id'   => Position::DIRT,
        ]);
        $response->assertJson([
            'status'         => 'not-trained',
            'position_title' => 'Training',
            'position_id'    => Position::DIRT_TRAINING,
        ]);
    }

    /*
     * Force an admin shift sign in for an untrained person.
     */

    public function testForceAdminSigninForPerson()
    {
        $this->createTrainingSession(false);
        $this->addRole(Role::ADMIN);
        $targetPersonId = $this->targetPerson->id;

        $response = $this->json('POST', 'timesheet/signin', [
            'person_id'     => $targetPersonId,
            'position_id'   => Position::DIRT,
        ]);

        $response->assertJson([
            'status'       => 'success',
            'forced'       => true
        ]);

        $timesheetId = $response->json('timesheet_id');
        $timesheet = Timesheet::findOrFail($timesheetId);
        $this->assertDatabaseHas('timesheet_log', [
            'timesheet_id'  => $timesheetId,
            'action'        => 'signon',
            'message'       => "forced (not trained Training) Dirt {$timesheet->on_duty}",
        ]);
    }

    /*
     * Signout a person
     */

    public function testSignoutPerson()
    {
        $timesheet = factory(Timesheet::class)->create([
            'person_id'   => $this->targetPerson->id,
            'on_duty'     => SqlHelper::now(),
            'position_id' => Position::DIRT,
        ]);

        $response = $this->json('POST', "timesheet/{$timesheet->id}/signoff");
        $response->assertStatus(200);

        $response->assertJson([
            'timesheet' => [
                'id'    => $timesheet->id
            ]
        ]);

        $timesheet->refresh();
        $this->assertFalse(!$timesheet->off_duty);

        $this->assertDatabaseHas('timesheet_log', [
            'timesheet_id'  => $timesheet->id,
            'action'        => 'signoff',
            'message'       => "Dirt {$timesheet->off_duty}",
            'create_person_id'  => $this->user->id,
        ]);
    }

    /*
     * Fail an attempt to sign out an already signed out timesheet.
     */

    public function testAlreadySignedout()
    {
        $response = $this->json('POST', "timesheet/{$this->timesheet->id}/signoff");
        $response->assertStatus(422);
        $response->assertJson([
             'errors' => [ [
                     'title' => 'Timesheet already signed off'
             ] ]
         ]);
    }

    /*
     * Verify the Clubhouse timesheet info is correct
     */

    public function testTimesheetInfo()
    {
        $person = $this->targetPerson;

        $this->setting('TimesheetCorrectionYear', 2010);
        $this->setting('TimesheetCorrectionEnable', true);

        $now = SqlHelper::now();
        $person->timesheet_confirmed = true;
        $person->timesheet_confirmed_at = $now;
        $person->saveWithoutValidation();

        $response = $this->json('GET', 'timesheet/info', [ 'person_id' => $this->targetPerson->id ]);
        $response->assertStatus(200);
        $response->assertJson([
             'info' => [
                 'correction_year'  => 2010,
                 'correction_enabled' => true,
                 'timesheet_confirmed'  => true,
                 'timesheet_confirmed_at' => $now
             ]
         ]);
    }

    /*
     * Confirm the entire timesheet for a person
     */

    public function testConfirmTimesheet()
    {
        $person = $this->targetPerson;

        $response = $this->json('POST', 'timesheet/confirm', [
            'person_id' => $person->id,
            'confirmed'   => true
        ]);

        $response->assertStatus(200);
        $person->refresh();

        $this->assertTrue($person->timesheet_confirmed == 1);
        $this->assertTrue($person->timesheet_confirmed_at != null);

        $this->assertDatabaseHas('timesheet_log', [
            'person_id' => $person->id,
            'action'    => 'confirmed',
            'message'   => 'confirmed'
        ]);
    }

    /*
     * Unconfirm the entire timesheet for a person
     */

    public function testUnconfirmTimesheet()
    {
        $person = $this->targetPerson;
        $person->timesheet_confirmed = 1;
        $person->timesheet_confirmed_at = SqlHelper::now();
        $person->saveWithoutValidation();

        $response = $this->json('POST', 'timesheet/confirm', [
            'person_id' => $person->id,
            'confirmed'   => false
        ]);

        $response->assertStatus(200);
        $person->refresh();

        $this->assertTrue($person->timesheet_confirmed == 0);
        $this->assertTrue($person->timesheet_confirmed_at == null);

        $this->assertDatabaseHas('timesheet_log', [
            'person_id' => $person->id,
            'action'    => 'confirmed',
            'message'   => 'unconfirmed'
        ]);
    }

    /*
     * Timesheet corrections report
     */

    public function testCorrectionRequests()
    {
        $timesheet = $this->timesheet;
        $timesheet->notes = 'Would thoust correctith thine entry?';
        $timesheet->saveWithoutValidation();

        $year = $this->year;
        $onDuty = date("$year-09-10 00:00:00");
        $offDuty = date("$year-09-10 13:00:00");

        $person = $this->targetPerson;

        factory(TimesheetMissing::class)->create([
             'on_duty'          => $onDuty,
             'off_duty'         => $offDuty,
             'person_id'        => $person->id,
             'create_person_id' => $this->user->id,
             'position_id'      => Position::DIRT,
             'notes'            => 'Give me hours!',
         ]);

        $response = $this->json('GET', 'timesheet/correction-requests', [ 'year' => $year ]);
        $response->assertStatus(200);

        $response->assertJson([
             'requests' => [
                 [
                     'notes' => 'Would thoust correctith thine entry?',
                     'person' => [
                         'id'   => $person->id,
                         'callsign' => $person->callsign
                     ]
                 ],
                 [
                      'on_duty'  => $onDuty,
                      'off_duty' => $offDuty,
                      'notes'       => 'Give me hours!',
                      'is_missing'  => true,
                      'person'   => [
                          'id'       => $person->id,
                          'callsign' => $person->callsign
                      ],
                  ]
            ],
         ]);
    }

    /*
     * Unconfirmed people report
     */

    public function testUnconfirmedPeopleReport()
    {
        // The test person will be unconfirmed.
        $person = $this->targetPerson;

        $response = $this->json('GET', 'timesheet/unconfirmed-people', [ 'year' => $this->year ]);
        $response->assertStatus(200);
        $response->assertJson([
             'unconfirmed_people' => [
                 [
                     'id'               => $person->id,
                     'callsign'         => $person->callsign,
                     'unverified_count' => 1,
                 ]
             ]
         ]);
    }

    /*
     * Shirt Earned Report
     */

    public function testShirtEarnedReport()
    {
        $year = $this->year;

        $this->setting('ShirtShortSleeveHoursThreshold', 8);
        $this->setting('ShirtLongSleeveHoursThreshold', 12);

        $ssPerson = factory(Person::class)->create([
            'longsleeveshirt_size_style' => 'Womens M',
            'teeshirt_size_style'        => 'Womens V-Neck M'
        ]);
        $lsPerson = factory(Person::class)->create();
        $unworkedPerson = factory(Person::class)->create();

        // Person only worked 4 hours
        factory(Timesheet::class)->create([
            'person_id' => $unworkedPerson->id,
            'position_id' => Position::DIRT,
            'on_duty'   => "$year-08-25 00:00:00",
            'off_duty'  => "$year-08-25 04:00:00"
        ]);

        // Person should have earned enough for a short slevee
        factory(Timesheet::class)->create([
            'person_id'   => $ssPerson->id,
            'position_id' => Position::DIRT,
            'on_duty'     => "$year-08-25 00:00:00",
            'off_duty'    => "$year-08-25 08:00:00"
        ]);

        // Person should have earned enough for a long slevee
        factory(Timesheet::class)->create([
            'person_id' => $lsPerson->id,
            'position_id' => Position::DIRT,
            'on_duty'   => "$year-08-25 00:00:00",
            'off_duty'  => "$year-08-25 12:00:00"
        ]);

        $response = $this->json('GET', 'timesheet/shirts-earned', [ 'year' => $year ]);
        $response->assertStatus(200);

        // Should return the settings
        $response->assertJson([
            'threshold_ss'  => 8,
            'threshold_ls'  => 12
        ]);

        $people = $response->json()['people'];
        $this->assertCount(2, $people);

        foreach ($people as $person) {
            if ($person['id'] == $lsPerson->id) {
                $this->assertTrue($person['earned_ls']);
                $this->assertTrue($person['earned_ss']);
                $this->assertEquals($person['hours'], 12);
            } elseif ($person['id'] == $ssPerson->id) {
                $this->assertFalse($person['earned_ls']);
                $this->assertTrue($person['earned_ss']);
                $this->assertEquals($person['hours'], 8);
            } else {
                $this->assertFalse(true, "Unknown id");
            }
        }
    }

    /*
     * The freaking years report
     */

    public function testFreakingYearsReport()
    {
        $prevYear = $this->year - 1;
        $person = $this->targetPerson;

        factory(Timesheet::class)->create([
            'person_id' => $person->id,
            'position_id' => Position::DIRT,
            'on_duty'   => "$prevYear-08-25 00:00:00",
            'off_duty'   => "$prevYear-08-25 01:00:00",
        ]);

        $response = $this->json('GET', 'timesheet/freaking-years', [ 'year' => $this->year ]);
        $response->assertStatus(200);
        $this->assertCount(1, $response->json()['freaking']);
        $response->assertJson([
            'freaking'  => [
                [
                    'years' => 2,
                    'people' => [[
                        'id'    => $person->id,
                        'status'     => $person->status,
                        'first_name' => $person->first_name,
                        'last_name'  => $person->last_name,
                        'callsign'  => $person->callsign,
                        'first_year' => $prevYear,
                        'last_year' => $this->year,
                    ]]
                ]
            ]
        ]);
    }

    /*
     * Radio Eligibility Report
     */


    public function testradioEligibilityReport()
    {
        $lastYear = $this->year - 1;
        $prevYear = $this->year - 2;
        $person = $this->targetPerson;

        factory(Timesheet::class)->create([
            'person_id' => $person->id,
            'position_id' => Position::DIRT,
            'on_duty'   => "$lastYear-08-25 00:00:00",
            'off_duty'   => "$lastYear-08-25 08:00:00",
        ]);


        factory(Timesheet::class)->create([
            'person_id' => $person->id,
            'position_id' => Position::DIRT,
            'on_duty'   => "$prevYear-08-25 00:00:00",
            'off_duty'   => "$prevYear-08-25 02:00:00",
        ]);

        $response = $this->json('GET', 'timesheet/radio-eligibility', [ 'year' => $this->year ]);
        $response->assertStatus(200);

        $response->assertJson([
            'people' => [[
                'id'              => $person->id,
                'callsign'        => $person->callsign,
                'hours_last_year' => 8,
                'hours_prev_year' => 2,
                'signed_up'       => false,
                'shift_lead'      => false
            ]]
        ]);
    }

    /*
     * Verify Bulk Sign In/Out
     */

    public function createBulkPeople()
    {
        $person1 = factory(Person::class)->create();
        factory(PersonPosition::class)->create([
             'person_id'    => $person1->id,
             'position_id'  => Position::DIRT,
         ]);

        $person2 = factory(Person::class)->create();
        factory(PersonPosition::class)->create([
             'person_id'    => $person2->id,
             'position_id'  => Position::HQ_WINDOW,
         ]);

        $this->person1 = $person1;
        $this->person2 = $person2;
    }

    /*
     * Verify a bulk sign in/out request
     */

    public function testVerifyBulkSigninOut()
    {
        $this->addRole(Role::ADMIN);
        $this->createBulkPeople();
        $person1 = $this->person1;
        $person2 = $this->person2;


        $response = $this->json('POST', 'timesheet/bulk-sign-in-out', [
             'lines' => "{$person1->callsign},dirt\n{$person2->callsign},hq window,0400,0600\n"
         ]);

        $response->assertStatus(200);
        $response->assertJson([ 'status' => 'success', 'commit' => false ]);
        $this->assertCount(2, $response->json()['entries']);

        $response->assertJson([
             'entries' => [
                [
                    'callsign'    => $person1->callsign,
                    'person_id'   => $person1->id,
                    'action'      => 'in',
                    'position_id' => Position::DIRT
                ],
                [
                    'callsign'    => $person2->callsign,
                    'person_id'   => $person2->id,
                    'action'      => 'inout',
                    'position_id' => Position::HQ_WINDOW
                ]
             ]
         ]);

        $this->assertDatabaseMissing('timesheet', [ 'person_id' => $person1->id ]);
        $this->assertDatabaseMissing('timesheet', [ 'person_id' => $person2->id ]);
    }

    /*
     * Commit a bulk sign in/out request
     */

    public function testCommitBulkSigninOut()
    {
        $this->addRole(Role::ADMIN);

        $this->createBulkPeople();
        $person1 = $this->person1;
        $person2 = $this->person2;


        $today = date("Y-m-d");
        $response = $this->json('POST', 'timesheet/bulk-sign-in-out', [
             'lines' => "{$person1->callsign},dirt\n{$person2->callsign},hq window,0400,0600\n",
             'commit'   => true
         ]);

        $response->assertStatus(200);
        $response->assertJson([
             'status' => 'success',
             'commit'   => true,
         ]);
        $this->assertCount(2, $response->json()['entries']);


        $response->assertJson([
             'entries' => [
                [
                    'callsign'    => $person1->callsign,
                    'person_id'   => $person1->id,
                    'action'      => 'in',
                    'position_id' => Position::DIRT
                ],
                [
                    'callsign'    => $person2->callsign,
                    'person_id'   => $person2->id,
                    'action'      => 'inout',
                    'position_id' => Position::HQ_WINDOW
                ]
             ]
         ]);

        $this->assertDatabaseHas('timesheet', [
             'person_id'   => $person1->id,
             'position_id' => Position::DIRT,
             'off_duty'    => null
         ]);

        $this->assertDatabaseHas('timesheet', [
             'person_id'   => $person2->id,
             'position_id' => Position::HQ_WINDOW,
             'on_duty'     => "$today 04:00:00",
             'off_duty'    => "$today 06:00:00"
         ]);
    }

    /*
     * Verification failed for Bulk Sign In/Out
     */

    public function testVerificationFailureBulkSigninOut()
    {
        $this->addRole(Role::ADMIN);
        $this->createBulkPeople();
        $person1 = $this->person1;
        $person2 = $this->person2;


        // Test for
        //  - not held position
        //  - end time before start time
        //  - unknown position
        //  - unknown callsign

        $response = $this->json('POST', 'timesheet/bulk-sign-in-out', [
             'lines' => "{$person1->callsign},hq window\n{$person1->callsign},dirt,08/14,0400,08/13,0600\n{$person2->callsign},rts at berlin\nhubcap,dirt\n"
         ]);

        $response->assertStatus(200);
        $response->assertJson([ 'status' => 'error', 'commit' => false ]);
        $this->assertCount(4, $response->json()['entries']);

        $response->assertJson([
             'entries' => [
                [
                    'callsign'    => $person1->callsign,
                    'errors'      => [ "does not hold the position 'HQ Window'" ],
                    'action'      => 'in',
                ],
                [
                    'callsign'    => $person1->callsign,
                    'person_id'   => $person1->id,
                    'action'      => 'inout',
                    'position_id' => Position::DIRT,
                    'errors'      => [ 'sign in time starts on or after sign out' ]
                ],
                [
                    'callsign'    => $person2->callsign,
                    'person_id'   => $person2->id,
                    'action'      => 'in',
                    'errors'      => [  "position 'rts at berlin' not found" ]
                ],
                [
                    'callsign'    => 'hubcap',
                    'person_id'   => null,
                    'action'      => 'in',
                    'errors'      => [  "callsign 'hubcap' not found" ]
                ]
             ]
         ]);
    }

    /*
     * Test Timesheet Sanity Checker
     */

    public function testTImesheetSanityChecker()
    {
        $this->addRole(Role::ADMIN);
        $year = current_year();

        $person = factory(Person::class)->create();
        $onDuty = factory(Timesheet::class)->create([
            'person_id'   => $person->id,
            'position_id' => Position::DIRT,
            'on_duty'     => date("Y-m-d 00:00:00")
        ]);

        $ymd = "$year-08-15";
        $endBeforeStart = factory(Timesheet::class)->make([
            'person_id'   => $person->id,
            'position_id' => Position::DIRT,
            'on_duty'     => date("$ymd 03:00:00"),
            'off_duty'    => date("$ymd 00:30:00")
        ]);
        $endBeforeStart->saveWithoutValidation();

        $ymd = "$year-08-16";
        $overlapFirst = factory(Timesheet::class)->create([
            'person_id'   => $person->id,
            'position_id' => Position::DIRT,
            'on_duty'     => date("$ymd 04:00:00"),
            'off_duty'    => date("$ymd 05:00:00")
        ]);
        $overlapSecond = factory(Timesheet::class)->create([
            'person_id'   => $person->id,
            'position_id' => Position::DIRT_GREEN_DOT,
            'on_duty'     => date("$ymd 04:30:00"),
            'off_duty'    => date("$ymd 05:30:00")
        ]);

        $ymd = "$year-08-17";
        $tooLong = factory(Timesheet::class)->create([
            'person_id'   => $person->id,
            'position_id' => Position::DIRT_GREEN_DOT,
            'on_duty'     => date("$year-08-17 04:30:00"),
            'off_duty'    => date("$year-08-18 04:30:00")
        ]);

        $response = $this->json('GET', 'timesheet/sanity-checker', [ 'year' => $year ]);
        $response->assertStatus(200);

        $response->assertJson([
            'on_duty' => [
                [
                    'person' => [ 'id' => $person->id ],
                    'on_duty'   => (string)$onDuty->on_duty,
                    'position'  => [
                        'id'    => $onDuty->position_id,
                    ]
                ]
            ],
            'end_before_start' => [
                [
                    'person'   => [ 'id' => $person->id ],
                    'on_duty'  => (string)$endBeforeStart->on_duty,
                    'off_duty' => (string)$endBeforeStart->off_duty,
                    'position' => [ 'id' => $endBeforeStart->position_id ]
                ]
            ],

            'overlapping' => [
                [
                    'person'   => [ 'id' => $person->id ],
                    'entries' => [
                        [
                            [
                                'on_duty'   => (string)$overlapFirst->on_duty,
                                'off_duty'  => (string)$overlapFirst->off_duty,
                                'position'  => [ 'id' => $overlapFirst->position_id ]
                            ],
                            [
                                'on_duty'   => (string)$overlapSecond->on_duty,
                                'off_duty'  => (string)$overlapSecond->off_duty,
                                'position'  => [ 'id' => $overlapSecond->position_id ]
                            ]
                        ]
                    ]
                ]
            ],

            'too_long' => [
                [
                    'person'   => [ 'id' => $tooLong->person_id ],
                    'position' => [ 'id' => $tooLong->position_id ],
                    'on_duty'  => (string)$tooLong->on_duty,
                    'off_duty' => (string)$tooLong->off_duty,
                ]
            ]

        ]);
    }

    /*
     * Test Thank You cards
     */

    public function testThankYouCards()
    {
        $password = 'thank you';
        $this->setting('ThankYouCardsHash', hash('sha256', $password));

        $this->addRole(Role::ADMIN);
        $year = date('Y');

        DB::table('timesheet')->delete();

        $person = factory(Person::class)->create();
        $timesheet = factory(Timesheet::class)->create([
            'person_id'   => $person->id,
            'position_id' => Position::DIRT,
            'on_duty'     => date("$year-m-d 01:00:00"),
            'off_duty'    => date("$year-m-d 02:00:00"),
        ]);

        $response = $this->json('GET', 'timesheet/thank-you', [ 'year' => $year, 'password' => $password ]);
        $response->assertStatus(200);

        $response->assertJson([
            'people' => [
                [
                    'id'         => $person->id,
                    'callsign'   => $person->callsign,
                    'first_name' => $person->first_name,
                    'last_name'  => $person->last_name
                ]
            ]
        ]);
    }

    /*
     * Test Thank You cards wrong password
     */

    public function testThankYouCardsWrongPassword()
    {
        $password = 'thank you';
        $this->setting('ThankYouCardsHash', hash('sha256', $password));

        $this->addRole(Role::ADMIN);
        $response = $this->json('GET', 'timesheet/thank-you', [ 'year' => date('Y'), 'password' => 'wrong password' ]);
        $response->assertStatus(403);
    }

    /*
     * Test Timesheet By Callsign report
     */

    public function testTimesheetByCallsign()
    {
        $year = 2010;

        $person = factory(Person::class)->create();

        $entry = factory(Timesheet::class)->create([
            'person_id' => $person->id,
            'position_id' => Position::DIRT,
            'on_duty'   => date("$year-08-25 00:00:00"),
            'off_duty'   => date("$year-08-25 01:00:00"),
        ]);

        $response = $this->json('GET', 'timesheet/by-callsign', [ 'year' => $year ]);
        $response->assertStatus(200);
        $response->assertJson([
            'people'  => [
                [
                    'id'         => $person->id,
                    'callsign'  => $person->callsign,
                    'status'     => $person->status,
                    'timesheet' => [
                        [
                            'position' => [
                                'id' => Position::DIRT,
                                'title' => 'Dirt'
                            ],
                            'on_duty' => (string)$entry->on_duty,
                            'off_duty' => (string)$entry->off_duty,
                            'duration' => 3600,
                        ]
                    ]
                ]
            ]
        ]);
    }
}
