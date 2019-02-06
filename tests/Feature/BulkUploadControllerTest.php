<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Models\AccessDocument;
use App\Models\Bmid;
use App\Models\Person;
use App\Models\RadioEligible;

class BulkUploadControllerTest extends TestCase
{
    use RefreshDatabase;

    /*
     * have each test have a fresh user that is logged in.
     */

    public function setUp()
    {
        parent::setUp();
        $this->signInUser();
        $this->addAdminRole();
    }

    /*
     * Test unknown callsigns are flagged
     */

    public function testUpdatePersonStatusWithUnknownCallsign()
    {
        $response = $this->json('POST', 'bulk-upload', [
            'action'    => 'prospective',
            'records'   => 'unknown-callsign',
            'commit'    => true,
        ]);

        $response->assertStatus(200);
        $response->assertJson([ 'results' => [
            [
                'callsign'  => 'unknown-callsign',
                'status'    => 'callsign-not-found',
            ]
        ]]);
    }

    /*
     * Test for an empty records fields
     */

    public function testUpdateEmptyRecord()
    {
        $response = $this->json('POST', 'bulk-upload', [
            'action'    => 'prospective',
            'records'   => "   \n\n\n",
            'commit'    => true,
        ]);

        $response->assertStatus(422);
    }

    /*
     * Test changing the status without commiting.
     */

    public function testUpdatePersonStatusWithoutCommit()
    {
        $person = factory(Person::class)->create([
            'status'    => 'prospective'
        ]);

        $response = $this->json('POST', 'bulk-upload', [
            'action'    => 'alpha',
            'records'   => $person->callsign,
        ]);

        $response->assertStatus(200);
        $response->assertJson([ 'results' => [
            [
                'callsign'  => $person->callsign,
                'status'    => 'success'
            ]
        ]]);

        $person->refresh();
        $this->assertEquals('prospective', $person->status);
    }

    /*
     * Test changing status with commit
     */

    public function testUpdatePersonStatusWithCommit()
    {
        $person = factory(Person::class)->create([
            'status'    => 'prospective'
        ]);

        $response = $this->json('POST', 'bulk-upload', [
            'action'    => 'alpha',
            'records'   => $person->callsign,
            'commit'    => true,
        ]);

        $response->assertStatus(200);
        $response->assertJson([ 'results' => [
            [
                'callsign'  => $person->callsign,
                'status'    => 'success',
                'changes'    => [ 'prospective', 'alpha' ]
            ]
        ]]);

        $person->refresh();
        $this->assertEquals('alpha', $person->status);
    }

    /*
     * Test setting a person column without commiting
     */

    public function testUpdatePersonColumnWithoutCommit()
    {
        $person = factory(Person::class)->create([
            'vehicle_insurance_paperwork' => 0,
        ]);

        $response = $this->json('POST', 'bulk-upload', [
            'action'    => 'vehicle_insurance_paperwork',
            'records'   => $person->callsign,
        ]);

        $response->assertStatus(200);
        $response->assertJson([ 'results' => [
            [
                'callsign'  => $person->callsign,
                'status'    => 'success',
            ]
        ]]);

        $person->refresh();
        $this->assertEquals(0, $person->vehicle_insurance_paperwork);
    }

    /*
     * Test setting a person column and commit
     */

    public function testUpdatePersonColumnWithCommit()
    {
        $person = factory(Person::class)->create([
            'vehicle_insurance_paperwork' => 0,
        ]);

        $response = $this->json('POST', 'bulk-upload', [
            'action'    => 'vehicle_insurance_paperwork',
            'records'   => $person->callsign,
            'commit'    => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJson([ 'results' => [
            [
                'callsign'  => $person->callsign,
                'status'    => 'success',
                'changes'    => [ 0, 1]
            ]
        ]]);

        $this->assertDatabaseHas('person', [
            'id' => $person->id,
            'vehicle_insurance_paperwork'     => 1
        ]);
    }

    /*
     * Test setting showers
     */

    public function testGrantShowersWithCommit()
    {
        $callsign = $this->user->callsign;

        $response = $this->json('POST', 'bulk-upload', [
            'action'    => 'showers',
            'records'   => "{$callsign},1",
            'commit'    => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJson([ 'results' => [
            [
                'callsign'  => $callsign,
                'status'    => 'success',
            ]
        ]]);

        $this->assertDatabaseHas('bmid', [
            'person_id' => $this->user->id,
            'showers'     => 1
        ]);
    }

    /*
     * Test setting showers
     */

    public function testGrantShowersWithoutCommit()
    {
        $callsign = $this->user->callsign;

        $response = $this->json('POST', 'bulk-upload', [
            'action'    => 'showers',
            'records'   => "{$callsign},1",
        ]);

        $response->assertStatus(200);
        $response->assertJson([ 'results' => [
            [
                'callsign'  => $callsign,
                'status'    => 'success',
            ]
        ]]);

        $this->assertDatabaseMissing('bmid', [
            'person_id' => $this->user->id,
            'showers'     => 1
        ]);
    }

    /*
     * Test revoking showers (oh noes!)
     */

    public function testRevokeShowersWithCommit()
    {
        $callsign = $this->user->callsign;

        factory(Bmid::class)->create([
            'person_id' => $this->user->id,
            'year'      => date('Y'),
            'showers'   => 1,
        ]);

        $response = $this->json('POST', 'bulk-upload', [
            'action'    => 'showers',
            'records'   => "{$callsign},0",
            'commit'    => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJson([ 'results' => [
            [
                'callsign'  => $callsign,
                'status'    => 'success',
            ]
        ]]);

        $this->assertDatabaseHas('bmid', [
            'person_id' => $this->user->id,
            'showers'     => 0
        ]);
    }

    /*
     * Test granting showers without commit (oh you tease!)
     */

    public function testRevokeShowersWithoutCommit()
    {
        $callsign = $this->user->callsign;

        $response = $this->json('POST', 'bulk-upload', [
            'action'    => 'showers',
            'records'   => "{$callsign},1",
        ]);

        $response->assertStatus(200);
        $response->assertJson([ 'results' => [
            [
                'callsign'  => $callsign,
                'status'    => 'success',
            ]
        ]]);

        $this->assertDatabaseMissing('bmid', [
            'person_id' => $this->user->id,
            'showers'     => 1
        ]);
    }

    /*
     * Test giving meals to a person ("And lo there was much rejoicing
     * concerning the eating of fresh leafy greens and gifting of cripsy bacon.")
     */

    public function testGrantingMealsWithCommit()
    {
        $callsign = $this->user->callsign;

        $response = $this->json('POST', 'bulk-upload', [
            'action'   => 'meals',
            'records'  => "$callsign,event+post",
            'commit'   => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJson([ 'results' => [
            [
                'callsign' => $callsign,
                'status'   => 'success'
            ]
        ]]);

        $this->assertDatabaseHas(
            'bmid',
            [ 'person_id' => $this->user->id, 'year' => date('Y'), 'meals' => 'event+post' ]
        );
    }

    /*
     * Test adding more meals to a person
     */

    public function testGrantingMoreMealsWithCommit()
    {
        $callsign = $this->user->callsign;
        $year = date('Y');

        factory(Bmid::class)->create([
            'person_id' => $this->user->id,
            'year'      => $year,
            'meals'     => 'pre+event'
        ]);

        $response = $this->json('POST', 'bulk-upload', [
            'action'   => 'meals',
            'records'  => "$callsign,+post",
            'commit'   => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJson([ 'results' => [
            [
                'callsign' => $callsign,
                'status'   => 'success'
            ]
        ]]);

        $this->assertDatabaseHas(
            'bmid',
            [ 'person_id' => $this->user->id, 'year' => $year, 'meals' => 'all' ]
        );
    }

    /*
     * Test marking BMID as submitted
     */

    public function testMarkBMIDAsSubmittedWithCommit()
    {
        $callsign = $this->user->callsign;
        $year = date('Y');

        factory(Bmid::class)->create([
            'person_id' => $this->user->id,
            'year'      => $year,
            'status'    => 'ready_to_print'
        ]);

        $response = $this->json('POST', 'bulk-upload', [
            'action'   => 'bmidsubmitted',
            'records'  => $callsign,
            'commit'   => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJson([ 'results' => [
            [
                'callsign' => $callsign,
                'status'   => 'success'
            ]
        ]]);

        $this->assertDatabaseHas(
            'bmid',
            [ 'person_id' => $this->user->id, 'year' => $year, 'status' => 'submitted' ]
        );
    }

    /*
     * Test submitting tickets
     */

    public function testSubmitTicketsWithCommit()
    {
        $year = date('Y');
        $callsign = $this->user->callsign;

        $response = $this->json('POST', 'bulk-upload', [
            'action'   => 'tickets',
            'records'  => "$callsign,CRED",
            'commit'   => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJson([ 'results' => [
            [
                'callsign' => $callsign,
                'status'   => 'success',
            ]
        ]]);

        $this->assertDatabaseHas(
            'access_document',
            [
                'person_id' => $this->user->id,
                'source_year' => $year - 1,
                'status' => 'qualified',
                'type' => 'staff_credential'
            ]
        );

        $types = [
            'RPT'   => 'reduced_price_ticket',
            'GIFT'  => 'gift_ticket',
            'VP'    => 'vehicle_pass',
            'WAP'   => 'work_access_pass',
        ];

        // Run through each type to make sure it works
        foreach ($types as $type => $adType) {
            $response = $this->json('POST', 'bulk-upload', [
                'action'   => 'tickets',
                'records'  => "$callsign,$type",
                'commit'   => 1,
            ]);

            $response->assertStatus(200);
            $response->assertJson([ 'results' => [
                [
                    'callsign' => $callsign,
                    'status'   => 'success',
                ]
            ]]);
            $this->assertDatabaseHas(
                'access_document',
                [
                    'person_id' => $this->user->id,
                    'source_year' => $year - 1,
                    'status' => 'qualified',
                    'type' => $adType
                ]
            );
        }
    }

    /*
     * Test submitting tickets
     */

    public function testSubmitTicketsWithDateAndCommit()
    {
        $year = date('Y');
        $callsign = $this->user->callsign;

        $response = $this->json('POST', 'bulk-upload', [
            'action'   => 'tickets',
            'records'  => "$callsign,CRED,$year-08-25",
            'commit'   => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJson([ 'results' => [
            [
                'callsign' => $callsign,
                'status'   => 'success',
            ]
        ]]);

        $this->assertDatabaseHas(
            'access_document',
            [
                'person_id' => $this->user->id,
                'type' => 'staff_credential',
                'access_date' => "$year-08-25",
            ]
        );
    }


    /*
     * Test setting WAP dates
     */

    public function testSetWAPWithCommit()
    {
        $year = date('Y');
        $callsign = $this->user->callsign;
        $personId = $this->user->id;

        factory(AccessDocument::class)->create([
            'person_id' => $personId,
            'type'      => 'work_access_pass',
        ]);

        $response = $this->json('POST', 'bulk-upload', [
            'action'   => 'wap',
            'records'  => "$callsign,$year-08-25",
            'commit'   => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJson([ 'results' => [
            [
                'callsign' => $callsign,
                'status'   => 'success',
            ]
        ]]);

        $this->assertDatabaseHas(
            'access_document',
            [
                'person_id' => $this->user->id,
                'type' => 'work_access_pass',
                'access_date' => "$year-08-25",
            ]
        );

        // Set with anytime date
        $response = $this->json('POST', 'bulk-upload', [
            'action'   => 'wap',
            'records'  => "$callsign,any",
            'commit'   => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJson([ 'results' => [
            [
                'callsign' => $callsign,
                'status'   => 'success',
            ]
        ]]);

        $this->assertDatabaseHas(
            'access_document',
            [
                'person_id' => $this->user->id,
                'type' => 'work_access_pass',
                'access_date' => null,
                'access_any_time' => true,
            ]
        );
    }

    /*
     * Test expect fail with no wap
     */

    public function testExpectFailWithNoWAP()
    {
        $year = date('Y');
        $callsign = $this->user->callsign;
        $personId = $this->user->id;

        $response = $this->json('POST', 'bulk-upload', [
            'action'   => 'wap',
            'records'  => "$callsign,$year-08-25",
            'commit'   => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJson([ 'results' => [
            [
                'callsign' => $callsign,
                'status'   => 'failed',
            ]
        ]]);

        $this->assertDatabaseMissing(
            'access_document',
            [
                'person_id' => $this->user->id,
                'type' => 'work_access_pass',
                'access_date' => "$year-08-25",
            ]
        );
    }

    /*
     * Test setting WAP dates
     */

    public function testSetMaxRadiosWithCommit()
    {
        $year = date('Y');
        $callsign = $this->user->callsign;
        $personId = $this->user->id;

        // Test default one radio.
        $response = $this->json('POST', 'bulk-upload', [
            'action'   => 'eventradio',
            'records'  => "$callsign",
            'commit'   => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJson([ 'results' => [
            [
                'callsign' => $callsign,
                'status'   => 'success',
            ]
        ]]);

        $this->assertDatabaseHas('radio_eligible', [
            'person_id' => $personId,
            'year'      => $year,
            'max_radios' => 1,
        ]);

        // Set specific number
        $response = $this->json('POST', 'bulk-upload', [
            'action'   => 'eventradio',
            'records'  => "$callsign,2",
            'commit'   => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJson([ 'results' => [
            [
                'callsign' => $callsign,
                'status'   => 'success',
            ]
        ]]);

        $this->assertDatabaseHas('radio_eligible', [
            'person_id' => $personId,
            'year'      => $year,
            'max_radios' => 2,
        ]);

    }
}
