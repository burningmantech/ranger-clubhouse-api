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
use App\Models\PersonEvent;

use App\Helpers\SqlHelper;

class TimesheetControllerTest extends TestCase
{
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
        Position::factory()->create(
            [
                'id'    => Position::DIRT,
                'title' => 'Dirt',
                'type'  => 'Frontline',
                'active' => false
            ]
        );

        Position::factory()->create(
            [
                'id'    => Position::TRAINING,
                'title' => 'Training',
                'type'  => 'Training',
            ]
        );

        // Setup default (real world) positions
        $this->hqWindow = Position::factory()->create(
            [
                'id'    => Position::HQ_WINDOW,
                'title' => 'HQ Window',
                'type'  => 'Frontline',
            ]
        );

        PositionCredit::factory()->create([
            'position_id'   => Position::DIRT,
            'start_time'    => date("$year-08-15 00:00:00"),
            'end_time'      => date("$year-09-02 00:00:00"),
            'credits_per_hour'  => 2.00,
            'description'   => 'All Dirt All The Time',
        ]);

        $this->targetPerson = Person::factory()->create();

        PersonPosition::factory()->create([
            'person_id' => $this->targetPerson->id,
            'position_id'   => Position::DIRT,
        ]);

        $onDuty = date("$year-08-25 06:00:00");
        $offDuty = date("$year-08-25 12:00:00");

        $this->timesheet = Timesheet::factory()->create([
            'person_id' => $this->targetPerson->id,
            'on_duty'   => $onDuty,
            'off_duty'  => $offDuty,
            'position_id'   => Position::DIRT,
        ]);

        TimesheetLog::factory()->create([
            'person_id'        => $this->targetPerson->id,
            'timesheet_id'     => $this->timesheet->id,
            'create_person_id' => $this->user->id,
            'action'           => 'signon',
            'created_at'       => $onDuty,
            'message'          => 'Dirt '.$onDuty
        ]);

        TimesheetLog::factory()->create([
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
        $slot = Slot::factory()->create([
            'begins'    => date("Y-01-01 00:00:00"),
            'ends'      => date('Y-01-01 01:00:00'),
            'position_id'  => Position::TRAINING,
        ]);

        PersonSlot::factory()->create([
            'person_id' => $this->targetPerson->id,
            'slot_id'   => $slot->id
        ]);

        TraineeStatus::factory()->create([
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
              'message'         => "on duty old $year-08-25 06:00:00 new $year-09-10 00:00:00, off duty old $year-08-25 12:00:00 new $year-09-10 13:00:00, position old Dirt new HQ Window",
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
            'position_id'    => Position::TRAINING,
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
        $timesheet = Timesheet::factory()->create([
            'person_id'   => $this->targetPerson->id,
            'on_duty'     => now(),
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
        $response->assertStatus(200);
        $response->assertJson([ 'status' => 'already-signed-off' ]);
    }

    /*
     * Verify the Clubhouse timesheet info is correct
     */

    public function testTimesheetInfo()
    {
        $person = $this->targetPerson;

        $year = current_year();
        $this->setting('TimesheetCorrectionYear', $year);
        $this->setting('TimesheetCorrectionEnable', true);

        $now = now();
        PersonEvent::factory()->create([
            'person_id' => $person->id,
            'year' => $year,
            'timesheet_confirmed' => true,
            'timesheet_confirmed_at' => $now
        ]);

        $response = $this->json('GET', 'timesheet/info', [ 'person_id' => $person->id ]);
        $response->assertStatus(200);
        $response->assertJson([
             'info' => [
                 'correction_year'  => $year,
                 'correction_enabled' => true,
                 'timesheet_confirmed'  => true,
                 'timesheet_confirmed_at' => (string)$now
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

        $this->assertDatabaseHas('person_event', [
            'person_id' => $person->id,
            'year' => current_year(),
            'timesheet_confirmed' => true
        ]);

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

        PersonEvent::factory()->create([
            'person_id' => $person->id,
            'year' => current_year(),
            'timesheet_confirmed' => true,
            'timesheet_confirmed_at' => now()
        ]);

        $response = $this->json('POST', 'timesheet/confirm', [
            'person_id' => $person->id,
            'confirmed'   => false
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('person_event', [
            'person_id' => $person->id,
            'year' => current_year(),
            'timesheet_confirmed' => false
        ]);
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

        TimesheetMissing::factory()->create([
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
     * Potential Shirt Earned Report
     */

    public function testPotentialShirtEarnedReport()
    {
        $year = $this->year;

        $this->setting('ShirtShortSleeveHoursThreshold', 8);
        $this->setting('ShirtLongSleeveHoursThreshold', 12);

        // Clear out timesheets created by setup
        // TODO Refactor reports into separate classes(?)
        DB::delete("delete from timesheet");

        // Person only worked 4 hours
        $fourHourPerson = Person::factory()->create();
        Timesheet::factory()->create([
            'person_id' => $fourHourPerson->id,
            'position_id' => Position::DIRT,
            'on_duty'   => "$year-08-25 00:00:00",
            'off_duty'  => "$year-08-25 04:00:00"
        ]);

        // Person should have earned enough for a short slevee
        $ssPerson = Person::factory()->create([
            'longsleeveshirt_size_style' => 'Womens M',
            'teeshirt_size_style'        => 'Womens V-Neck M'
        ]);
        Timesheet::factory()->create([
            'person_id'   => $ssPerson->id,
            'position_id' => Position::DIRT,
            'on_duty'     => "$year-08-25 00:00:00",
            'off_duty'    => "$year-08-25 08:00:00"
        ]);

        // Person should have earned enough for a long slevee
        $lsPerson = Person::factory()->create();
        Timesheet::factory()->create([
            'person_id' => $lsPerson->id,
            'position_id' => Position::DIRT,
            'on_duty'   => "$year-08-25 00:00:00",
            'off_duty'  => "$year-08-25 12:00:00"
        ]);

        // Shiny Penny should not appear in report
        $shinyPenny = Person::factory()->create();
        Timesheet::factory()->create([
            'person_id' => $shinyPenny->id,
            'position_id' => Position::ALPHA,
            'on_duty'   => "$year-08-25 00:00:00",
            'off_duty'  => "$year-08-25 12:00:00"
        ]);

        Timesheet::factory()->create([
            'person_id' => $shinyPenny->id,
            'position_id' => Position::DIRT_SHINY_PENNY,
            'on_duty'   => "$year-08-26 00:00:00",
            'off_duty'  => "$year-08-26 12:00:00"
        ]);

        Timesheet::factory()->create([
            'person_id' => $shinyPenny->id,
            'position_id' => Position::BURN_PERIMETER,
            'on_duty'   => "$year-08-26 00:00:00",
            'off_duty'  => "$year-08-26 5:00:00"
        ]);

        // Person with potentail hours
        $potentialPerson = Person::factory()->create();
        $slot = Slot::factory()->create([
            'position_id' => Position::DIRT,
            'begins'   => "$year-08-25 00:00:00",
            'ends'  => "$year-08-25 10:00:00"
        ]);

        PersonSlot::factory()->create([
          'person_id' => $potentialPerson->id,
          'slot_id' => $slot->id
        ]);

        $response = $this->json('GET', 'timesheet/potential-shirts-earned', [ 'year' => $year ]);
        $response->assertStatus(200);

        // Should return the settings
        $response->assertJson([
            'threshold_ss'  => 8,
            'threshold_ls'  => 12
        ]);

        $people = $response->json()['people'];
        $this->assertCount(4, $people);

        foreach ($people as $person) {
            if ($person['id'] == $lsPerson->id) {
                $this->assertTrue($person['earned_ls']);
                $this->assertTrue($person['earned_ss']);
                $this->assertEquals($person['actual_hours'], 12);
            } elseif ($person['id'] == $ssPerson->id) {
                $this->assertFalse($person['earned_ls']);
                $this->assertTrue($person['earned_ss']);
                $this->assertEquals($person['actual_hours'], 8);
            } elseif ($person['id'] == $potentialPerson->id) {
                $this->assertFalse($person['earned_ls']);
                $this->assertFalse($person['earned_ss']);
                $this->assertEquals($person['estimated_hours'], 10);
            } elseif ($person['id'] == $fourHourPerson->id) {
                $this->assertFalse($person['earned_ls']);
                $this->assertFalse($person['earned_ss']);
                $this->assertEquals($person['actual_hours'], 4);
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

        Timesheet::factory()->create([
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

        Timesheet::factory()->create([
            'person_id' => $person->id,
            'position_id' => Position::DIRT,
            'on_duty'   => "$lastYear-08-25 00:00:00",
            'off_duty'   => "$lastYear-08-25 08:00:00",
        ]);


        Timesheet::factory()->create([
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
        $person1 = Person::factory()->create();
        PersonPosition::factory()->create([
             'person_id'    => $person1->id,
             'position_id'  => Position::DIRT,
         ]);

        $person2 = Person::factory()->create();
        PersonPosition::factory()->create([
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

        $person = Person::factory()->create();
        $onDuty = Timesheet::factory()->create([
            'person_id'   => $person->id,
            'position_id' => Position::DIRT,
            'on_duty'     => date("Y-m-d 00:00:00")
        ]);

        $ymd = "$year-08-15";
        $endBeforeStart = Timesheet::factory()->make([
            'person_id'   => $person->id,
            'position_id' => Position::DIRT,
            'on_duty'     => date("$ymd 03:00:00"),
            'off_duty'    => date("$ymd 00:30:00")
        ]);
        $endBeforeStart->saveWithoutValidation();

        $ymd = "$year-08-16";
        $overlapFirst = Timesheet::factory()->create([
            'person_id'   => $person->id,
            'position_id' => Position::DIRT,
            'on_duty'     => date("$ymd 04:00:00"),
            'off_duty'    => date("$ymd 05:00:00")
        ]);
        $overlapSecond = Timesheet::factory()->create([
            'person_id'   => $person->id,
            'position_id' => Position::DIRT_GREEN_DOT,
            'on_duty'     => date("$ymd 04:30:00"),
            'off_duty'    => date("$ymd 05:30:00")
        ]);

        $ymd = "$year-08-17";
        $tooLong = Timesheet::factory()->create([
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

        $person = Person::factory()->create();
        $timesheet = Timesheet::factory()->create([
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

        $person = Person::factory()->create();

        $entry = Timesheet::factory()->create([
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
                    'id'        => $person->id,
                    'callsign'  => $person->callsign,
                    'status'    => $person->status,
                    'timesheet' => [
                        [
                            'position' => [
                                'id' => Position::DIRT,
                                'title' => 'Dirt',
                                'active'=> false,
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

    /*
     * Test the Timesheet Totals report
     */

    public function testTimesheetTotalsReport()
    {
        $year = date('Y');

        $personA = Person::factory()->create([ 'callsign' => 'A' ]);
        $personB = Person::factory()->create([ 'callsign' => 'B' ]);

        // Clear out the default timesheets created in setUp()
        Timesheet::query()->delete();

        Timesheet::factory()->create([
            'person_id'    => $personA->id,
            'on_duty'      => date('Y-08-20 10:00:00'),
            'off_duty'     => date('Y-08-20 11:00:00'),
            'position_id'  => Position::DIRT
        ]);

        Timesheet::factory()->create([
            'person_id'    => $personB->id,
            'on_duty'      => date('Y-08-20 10:00:00'),
            'off_duty'     => date('Y-08-20 12:00:00'),
            'position_id'  => Position::HQ_WINDOW
        ]);

        $response = $this->json('GET', 'timesheet/totals', [ 'year' => $year ]);
        $response->assertStatus(200);

        $response->assertJsonCount(2, 'people.*.id');

        $response->assertJson([
            'people' => [
                [
                    'id'        => $personA->id,
                    'callsign'  => $personA->callsign,
                    'status'    => $personA->status,
                    'positions' => [
                        [
                            'id'   => Position::DIRT,
                            'duration' => 3600
                        ]
                    ]
                ],
                [
                    'id'        => $personB->id,
                    'callsign'  => $personB->callsign,
                    'status'    => $personB->status,
                    'positions' => [
                        [
                            'id'   => Position::HQ_WINDOW,
                            'duration' => 7200
                        ]
                    ]
                ]
            ]
        ]);
    }

     /*
      * Test the Timesheet By Position report
      */

    public function testTimesheetByPositionReport()
    {
        $year = date('Y');

        $personA = Person::factory()->create([ 'callsign' => 'A' ]);
        $personB = Person::factory()->create([ 'callsign' => 'B' ]);

        // Clear out the default timesheets created in setUp()
        Timesheet::query()->delete();

        $entryA = Timesheet::factory()->create([
            'person_id'    => $personA->id,
            'on_duty'      => date('Y-08-20 10:00:00'),
            'off_duty'     => date('Y-08-20 11:00:00'),
            'position_id'  => Position::DIRT
        ]);

        $entryB = Timesheet::factory()->create([
            'person_id'    => $personB->id,
            'on_duty'      => date('Y-08-20 10:00:00'),
            'off_duty'     => date('Y-08-20 12:00:00'),
            'position_id'  => Position::HQ_WINDOW
        ]);

        $response = $this->json('GET', 'timesheet/by-position', [ 'year' => $year ]);
        $response->assertStatus(200);

        $response->assertJsonCount(2, 'positions.*.id');

        $response->assertJson([
            'positions' => [
                [
                    'id'     => Position::DIRT,
                    'title'  => 'Dirt',
                    'active' => false,
                    'timesheets' => [[
                            'person' => [
                                'id'        => $personA->id,
                                'callsign'  => $personA->callsign,
                                'status'    => $personA->status,
                            ],
                            'on_duty'  => (string) $entryA->on_duty,
                            'off_duty' => (string) $entryA->off_duty,
                            'duration' => 3600
                    ]],
                ],
                [
                    'id'     => Position::HQ_WINDOW,
                    'title'  => 'HQ Window',
                    'active' => true,
                    'timesheets' => [[
                            'person' => [
                                'id'        => $personB->id,
                                'callsign'  => $personB->callsign,
                                'status'    => $personB->status,
                            ],
                            'on_duty'  => (string) $entryB->on_duty,
                            'off_duty' => (string) $entryB->off_duty,
                            'duration' => 7200
                    ]]
                ]
            ]
        ]);
    }
}
