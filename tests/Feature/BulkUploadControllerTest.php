<?php

namespace Tests\Feature;

use App\Models\AccessDocument;
use App\Models\Bmid;
use App\Models\Person;
use App\Models\PersonEvent;
use App\Models\PersonPosition;
use App\Models\Position;
use App\Models\Provision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkUploadControllerTest extends TestCase
{
    use RefreshDatabase;

    /*
     * have each test have a fresh user that is logged in.
     */

    public function setUp(): void
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
            'action' => 'prospective',
            'records' => 'unknown-callsign',
            'commit' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['results' => [
            [
                'callsign' => 'unknown-callsign',
                'status' => 'callsign-not-found',
            ]
        ]]);
    }

    /*
     * Test for an empty records fields
     */

    public function testUpdateEmptyRecord()
    {
        $response = $this->json('POST', 'bulk-upload', [
            'action' => 'prospective',
            'records' => "   \n\n\n",
            'commit' => true,
        ]);

        $response->assertStatus(422);
    }

    /*
     * Test changing the status without commiting.
     */

    public function testUpdatePersonStatusWithoutCommit()
    {
        $person = Person::factory()->create([
            'status' => 'prospective'
        ]);

        $response = $this->json('POST', 'bulk-upload', [
            'action' => 'alpha',
            'records' => $person->callsign,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['results' => [
            [
                'callsign' => $person->callsign,
                'status' => 'success'
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
        $person = Person::factory()->create([
            'status' => 'prospective'
        ]);

        $response = $this->json('POST', 'bulk-upload', [
            'action' => 'alpha',
            'records' => $person->callsign,
            'commit' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['results' => [
            [
                'callsign' => $person->callsign,
                'status' => 'success',
                'changes' => ['prospective', 'alpha']
            ]
        ]]);

        $person->refresh();
        $this->assertEquals('alpha', $person->status);
        $this->assertDatabaseHas('person_position', [
            'person_id' => $person->id,
            'position_id' => Position::ALPHA
        ]);
    }

    /*
     * Test changing status from alpha to active
     */

    public function testUpdatePersonStatusAlphaToActive()
    {
        $person = Person::factory()->create([
            'status' => 'alpha'
        ]);

        PersonPosition::factory()->create([
            'person_id' => $person->id,
            'position_id' => Position::ALPHA
        ]);

        $response = $this->json('POST', 'bulk-upload', [
            'action' => 'active',
            'records' => $person->callsign,
            'commit' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['results' => [
            [
                'callsign' => $person->callsign,
                'status' => 'success',
                'changes' => ['alpha', 'active']
            ]
        ]]);

        $person->refresh();
        $this->assertEquals('active', $person->status);
        $this->assertDatabaseMissing('person_position', [
            'person_id' => $person->id,
            'position_id' => Position::ALPHA
        ]);
    }

    /*
     * Test setting a person column without commiting
     */

    public function testUpdatePersonEventColumnWithoutCommit()
    {
        $year = current_year();
        $person = Person::factory()->create([]);
        $personEvent = PersonEvent::factory()->create(['year' => $year, 'person_id' => $person->id, 'org_vehicle_insurance' => false]);

        $response = $this->json('POST', 'bulk-upload', [
            'action' => 'org_vehicle_insurance',
            'records' => $person->callsign,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['results' => [
            [
                'callsign' => $person->callsign,
                'status' => 'success',
            ]
        ]]);

        $this->assertDatabaseHas('person_event', ['year' => $year, 'person_id' => $person->id, 'org_vehicle_insurance' => false]);
    }

    /*
     * Test setting a person column and commit
     */

    public function testUpdatePersonEventColumnWithCommit()
    {
        $year = current_year();
        $person = Person::factory()->create([]);
        $personEvent = PersonEvent::factory()->create(['year' => $year, 'person_id' => $person->id, 'org_vehicle_insurance' => false]);

        $response = $this->json('POST', 'bulk-upload', [
            'action' => 'org_vehicle_insurance',
            'records' => $person->callsign,
            'commit' => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['results' => [
            [
                'callsign' => $person->callsign,
                'status' => 'success',
                'changes' => [0, 1]
            ]
        ]]);

        $this->assertDatabaseHas('person_event', [
            'year' => $year,
            'person_id' => $person->id,
            'org_vehicle_insurance' => 1
        ]);
    }

    /*
     * Test setting showers
     */

    public function testGrantShowersWithCommit()
    {
        $callsign = $this->user->callsign;

        $response = $this->json('POST', 'bulk-upload', [
            'action' => 'showers',
            'records' => "{$callsign},1",
            'commit' => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['results' => [
            [
                'callsign' => $callsign,
                'status' => 'success',
            ]
        ]]);

        $this->assertDatabaseHas('bmid', [
            'person_id' => $this->user->id,
            'showers' => 1
        ]);
    }

    /*
     * Test setting showers
     */

    public function testGrantShowersWithoutCommit()
    {
        $callsign = $this->user->callsign;

        $response = $this->json('POST', 'bulk-upload', [
            'action' => 'showers',
            'records' => "{$callsign},1",
        ]);

        $response->assertStatus(200);
        $response->assertJson(['results' => [
            [
                'callsign' => $callsign,
                'status' => 'success',
            ]
        ]]);

        $this->assertDatabaseMissing('bmid', [
            'person_id' => $this->user->id,
            'showers' => 1
        ]);
    }

    /*
     * Test revoking showers (oh noes!)
     */

    public function testRevokeShowersWithCommit()
    {
        $callsign = $this->user->callsign;

        Bmid::factory()->create([
            'person_id' => $this->user->id,
            'year' => date('Y'),
            'showers' => 1,
        ]);

        $response = $this->json('POST', 'bulk-upload', [
            'action' => 'showers',
            'records' => "{$callsign},0",
            'commit' => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['results' => [
            [
                'callsign' => $callsign,
                'status' => 'success',
            ]
        ]]);

        $this->assertDatabaseHas('bmid', [
            'person_id' => $this->user->id,
            'showers' => 0
        ]);
    }

    /*
     * Test granting showers without commit (oh you tease!)
     */

    public function testRevokeShowersWithoutCommit()
    {
        $callsign = $this->user->callsign;

        $response = $this->json('POST', 'bulk-upload', [
            'action' => 'showers',
            'records' => "{$callsign},1",
        ]);

        $response->assertStatus(200);
        $response->assertJson(['results' => [
            [
                'callsign' => $callsign,
                'status' => 'success',
            ]
        ]]);

        $this->assertDatabaseMissing('bmid', [
            'person_id' => $this->user->id,
            'showers' => 1
        ]);
    }

    /*
     * Test giving meals to a person ("And lo there was much rejoicing
     * concerning the eating of fresh leafy greens and gifting of cripsy bacon.")
     */

    public function testGrantingMealsWithCommit()
    {
        $callsign = $this->user->callsign;
        $year = date('Y');

        $response = $this->json('POST', 'bulk-upload', [
            'action' => 'meals',
            'records' => "$callsign,event+post",
            'commit' => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['results' => [
            [
                'callsign' => $callsign,
                'status' => 'success'
            ]
        ]]);

        $this->assertDatabaseHas(
            'bmid',
            ['person_id' => $this->user->id, 'year' => $year, 'meals' => 'event+post']
        );

        // The meals should turn into all if already has event+post, and pre is added.
        $response = $this->json('POST', 'bulk-upload', [
            'action' => 'meals',
            'records' => "$callsign,+pre",
            'commit' => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['results' => [
            [
                'callsign' => $callsign,
                'status' => 'success'
            ]
        ]]);
        $this->assertDatabaseHas(
            'bmid',
            ['person_id' => $this->user->id, 'year' => $year, 'meals' => 'all']
        );

    }

    /*
     * Test adding all meals to a person
     */

    public function testGrantingAllMealsWithCommit()
    {
        $callsign = $this->user->callsign;
        $year = date('Y');

        Bmid::factory()->create([
            'person_id' => $this->user->id,
            'year' => $year,
            'meals' => 'event'
        ]);

        $response = $this->json('POST', 'bulk-upload', [
            'action' => 'meals',
            'records' => "$callsign,all",
            'commit' => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['results' => [
            [
                'callsign' => $callsign,
                'status' => 'success'
            ]
        ]]);

        $this->assertDatabaseHas(
            'bmid',
            ['person_id' => $this->user->id, 'year' => $year, 'meals' => 'all']
        );
    }

    /*
     * Test marking BMID as submitted
     */

    public function testMarkBMIDAsSubmittedWithCommit()
    {
        $callsign = $this->user->callsign;
        $year = date('Y');

        Bmid::factory()->create([
            'person_id' => $this->user->id,
            'year' => $year,
            'status' => 'ready_to_print'
        ]);

        $response = $this->json('POST', 'bulk-upload', [
            'action' => 'bmidsubmitted',
            'records' => $callsign,
            'commit' => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['results' => [
            [
                'callsign' => $callsign,
                'status' => 'success'
            ]
        ]]);

        $this->assertDatabaseHas(
            'bmid',
            ['person_id' => $this->user->id, 'year' => $year, 'status' => 'submitted']
        );
    }

    /*
     * Test submitting tickets
     */

    public function testSubmitTicketsWithCommit()
    {
        $year = (int)date('Y');
        $callsign = $this->user->callsign;

        $response = $this->json('POST', 'bulk-upload', [
            'action' => 'tickets',
            'records' => "$callsign,CRED",
            'commit' => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['results' => [
            [
                'callsign' => $callsign,
                'status' => 'success',
            ]
        ]]);

        $this->assertDatabaseHas(
            'access_document',
            [
                'person_id' => $this->user->id,
                'source_year' => $year - 1,
                'status' => 'qualified',
                'type' => AccessDocument::STAFF_CREDENTIAL,
            ]
        );

        $types = [
            'SPT' => AccessDocument::SPT,
            'GIFT' => AccessDocument::GIFT,
            'LSD' => AccessDocument::LSD,
            'VP' => AccessDocument::VEHICLE_PASS_SP,
            'VPGIFT' => AccessDocument::VEHICLE_PASS_GIFT,
            'WAP' => AccessDocument::WAP,
        ];

        // Run through each type to make sure it works
        foreach ($types as $type => $adType) {
            $response = $this->json('POST', 'bulk-upload', [
                'action' => 'tickets',
                'records' => "$callsign,$type",
                'commit' => 1,
            ]);

            $response->assertStatus(200);
            $response->assertJson(['results' => [
                [
                    'callsign' => $callsign,
                    'status' => 'success',
                ]
            ]]);
            $this->assertDatabaseHas(
                'access_document',
                [
                    'person_id' => $this->user->id,
                    // LSD & Gift default to the current year for source.
                    'source_year' => ($adType == AccessDocument::LSD || $adType == AccessDocument::GIFT || $adType == AccessDocument::VEHICLE_PASS_GIFT || $adType == AccessDocument::WAP) ? $year : $year - 1,
                    // LSD tickets are claimed.
                    'status' => ($adType == AccessDocument::LSD) ? AccessDocument::CLAIMED : AccessDocument::QUALIFIED,
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
            'action' => 'tickets',
            'records' => "$callsign,CRED,$year-08-25",
            'commit' => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['results' => [
            [
                'callsign' => $callsign,
                'status' => 'success',
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

        $this->setting('TAS_WAPDateRange', '3-28');

        AccessDocument::factory()->create([
            'person_id' => $personId,
            'type' => 'work_access_pass',
        ]);

        $response = $this->json('POST', 'bulk-upload', [
            'action' => 'wap',
            'records' => "$callsign,$year-08-25",
            'commit' => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['results' => [
            [
                'callsign' => $callsign,
                'status' => 'success',
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
            'action' => 'wap',
            'records' => "$callsign,any",
            'commit' => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['results' => [
            [
                'callsign' => $callsign,
                'status' => 'success',
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

        $this->setting('TAS_WAPDateRange', '3-28');

        $response = $this->json('POST', 'bulk-upload', [
            'action' => 'wap',
            'records' => "$callsign,$year-08-25",
            'commit' => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['results' => [
            [
                'callsign' => $callsign,
                'status' => 'failed',
            ]
        ]]);

        $this->assertDatabaseMissing(
            'access_document',
            [
                'person_id' => $personId,
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
            'action' => 'event_radio',
            'records' => "$callsign",
            'commit' => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['results' => [
            [
                'callsign' => $callsign,
                'status' => 'success',
            ]
        ]]);

        $this->assertDatabaseHas('provision', [
            'person_id' => $personId,
            'source_year' => $year - 1,
            'type' => Provision::EVENT_RADIO,
            'item_count' => 1,
        ]);

        // Set specific number
        $response = $this->json('POST', 'bulk-upload', [
            'action' => 'event_radio',
            'records' => "$callsign,2",
            'commit' => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['results' => [
            [
                'callsign' => $callsign,
                'status' => 'warning',
            ]
        ]]);

        $this->assertDatabaseHas('provision', [
            'person_id' => $personId,
            'source_year' => $year - 1,
            'type' => Provision::EVENT_RADIO,
            'item_count' => 2,
        ]);
    }
}
