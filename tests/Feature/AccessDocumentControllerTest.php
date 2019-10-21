<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Models\AccessDocument;
use App\Models\Person;
use App\Models\PersonPosition;
use App\Models\PersonSlot;
use App\Models\Position;
use App\Models\Role;
use App\Models\Slot;
use App\Models\Timesheet;

class AccessDocumentControllerTest extends TestCase
{
    use RefreshDatabase;

    /*
     * have each test have a fresh user that is logged in.
     */

    public function setUp() : void
    {
        parent::setUp();
        $this->signInUser();
    }

    private function createAccessDocument()
    {
        return factory(AccessDocument::class)->create([
            'type'        => 'staff_credential',
            'status'      => 'qualified',
            'person_id'   => $this->user->id,
            'source_year' => (int) date('Y'),
            'expiry_date' => date('Y-12-31'),
        ]);
    }

    /*
     * Test showing an access document
     */

    public function testShowAccessDocumentSuccess()
    {
        $ad = $this->createAccessDocument();

        $response = $this->json('GET', "access-document/{$ad->id}");
        $response->assertStatus(200);
        $response->assertJson([
            'access_document' => [
                'id'          => $ad->id,
                'type'        => $ad->type,
                'status'      => $ad->status,
                'source_year' => $ad->source_year,
                'expiry_date' => date('Y-12-31')
            ]
        ]);
    }

    /*
     * Test not finding an access document
     */

    public function testShowNonExistentAccessDocumentFailure()
    {
        $ad = $this->createAccessDocument();

        $response = $this->json('GET', "access-document/99999999");
        $response->assertStatus(404);
    }

    /*
     * Test creating an access document
     */

    public function testCreateAccessDocumentSuccess()
    {
        $this->addAdminRole();

        $data = [
            'person_id'   => $this->user->id,
            'type'        => 'staff_credential',
            'status'      => 'qualified',
            'source_year' => date('Y'),
            'expiry_date' => date('Y-12-31'),
        ];

        $response = $this->json('POST', 'access-document', [ 'access_document' => $data ]);
        $response->assertStatus(200);
        $this->assertDatabaseHas('access_document', $data);
    }

    /*
     * Update an access Document for allowed user
     */

    public function testAccessDocumentUpdateSuccess()
    {
        $ad = $this->createAccessDocument();

        $response = $this->json('PUT', "access-document/{$ad->id}", [ 'access_document' => [
            'status' => 'banked'
        ]]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('access_document', [
            'id'    => $ad->id,
            'status'   => 'banked'
        ]);
    }


    /*
     * Delete an access document
     */

    public function testAccessDocumentDeleteSuccess()
    {
        $this->addAdminRole();
        $ad = $this->createAccessDocument();

        $response = $this->json('DELETE', "access-document/{$ad->id}");
        $response->assertStatus(204);
        $this->assertDatabaseMissing('access_document', [ 'id' => $ad->id ]);
    }

    /*
     * Test changing the status on an access document
     */

    public function testStatusChangeSuccess()
    {
        $ad = $this->createAccessDocument();

        $response = $this->json('PATCH', "access-document/{$ad->id}/status", [ 'status' => 'banked' ]);
        $response->assertStatus(200);
        $this->assertDatabaseHas('access_document', [ 'id' => $ad->id, 'status' => 'banked' ]);
    }

    /*
     * Test retrieving the current access documents
     */

    public function testCurrentAccessDocumentSuccess()
    {
        $this->addAdminRole();

        $this->setting('TAS_WAPDateRange', '3-24');

        $ad = $this->createAccessDocument();

        $response = $this->json('GET', 'access-document/current');
        $response->assertStatus(200);
        $response->assertJson([
            'documents' => [
                'people' => [
                    [
                        'person' => [
                            'id'    => $this->user->id,
                            'callsign' => $this->user->callsign
                        ],

                        'documents' => [
                            [
                                'id'    => $ad->id,
                                'type'  => $ad->type
                            ]
                        ]
                    ]
                ],
                'day_low' => 3,
                'day_high' => 24,
            ],
        ]);
    }

    /*
     * Test retrieving expiring access documents
     */

    public function testRetrieveExpiringAccessDocuments()
    {
        $this->addAdminRole();

        $lastYear = date('Y') - 1;

        $expiring = factory(AccessDocument::class)->create([
             'person_id'   => $this->user->id,
             'type'        => 'staff_credential',
             'status'      => 'qualified',
             'source_year' => date('Y'),
             'expiry_date' => date("Y-12-31"),
         ]);

        $response = $this->json('GET', 'access-document/expiring');
        $response->assertStatus(200);
        $response->assertJson([
             'expiring' => [
                 [
                     'person' => [
                         'id'   => $this->user->id,
                         'callsign' => $this->user->callsign
                     ],
                     'tickets' => [
                         [
                             'id'   => $expiring->id,
                         ]
                     ]
                 ]
             ]
         ]);
    }

    /*
     * Test marking a batch of Access Documents as submitted.
     */

    public function testMarkSubmittedSuccess() {
        $this->addAdminRole();

        $ad = $this->createAccessDocument();
        $ad->update([ 'status' => 'claimed' ]);

        $response = $this->json('POST', 'access-document/mark-submitted', [ 'ids' => [ $ad->id ] ]);
        $response->assertStatus(200);
        $this->assertDatabaseHas('access_document', [ 'id' => $ad->id, 'status' => 'submitted' ]);
    }

    /*
     * Test Grant WAPs
     */

    public function testGrantWAPs()
    {
        $this->addAdminRole();

        $year = date('Y') - 2;
        $active = factory(Person::class)->create([ 'callsign' => 'A' ]);
        $timesheet = factory(Timesheet::class)->create([
              'person_id'   => $active->id,
              'position_id' => Position::DIRT,
              'on_duty'     => date("$year-08-25 12:00:00"),
              'off_duty'    => date("$year-08-25 13:00:00")
          ]);

        // Person should not be granted a second WAP.
        $noWap = factory(Person::class)->create([ 'callsign' => 'No Wap' ]);
        $timesheet = factory(Timesheet::class)->create([
              'person_id'   => $active->id,
              'position_id' => Position::DIRT,
              'on_duty'     => date("$year-08-25 12:00:00"),
              'off_duty'    => date("$year-08-25 13:00:00")
          ]);
        factory(AccessDocument::class)->create([
              'source_year' => date('Y'),
              'person_id'   => $noWap->id,
              'type'        => 'work_access_pass',
              'status'      => 'qualified'
          ]);

        $retired = factory(Person::class)->create([ 'status' => 'retired', 'callsign' => 'B' ]);
        $slot = factory(Slot::class)->create([
              'position_id' => Position::TRAINING,
              'begins'  => date('Y-04-01 10:00:00'),
              'ends'    => date('Y-04-01 12:00:00'),
              'max' => 2,
              'description' => 'A Training'
          ]);
        factory(PersonSlot::class)->create([ 'person_id' => $retired->id, 'slot_id' => $slot->id ]);

        $response = $this->json('POST', 'access-document/grant-waps');
        $response->assertStatus(200);

        $response->assertJsonCount(2, 'people.*.id');
        $response->assertJson([
              'people' => [
                  [
                      'id'  => $active->id,
                      'callsign' => $active->callsign,
                  ],
                  [
                      'id' => $retired->id,
                      'callsign' => $retired->callsign
                  ]
              ]
          ]);

        $this->assertDatabaseHas('access_document', [
              'person_id'   => $active->id,
              'type'    => 'work_access_pass'
          ]);

        $this->assertDatabaseHas('access_document', [
              'person_id'   => $retired->id,
              'type'    => 'work_access_pass'
          ]);

        // noWap person should not have been issued a second wap.
        $this->assertEquals(AccessDocument::where('person_id', $noWap->id)->count(), 1);
    }

    /*
     * Test Grant Alpha WAPs
     */

    public function testGrantAlphaWAPs()
    {
        $this->addAdminRole();

        $this->setting('TAS_DefaultAlphaWAPDate', date('Y-08-20'));
        $alpha = factory(Person::class)->create([ 'status' => 'alpha', 'callsign' => 'A' ]);

        // Alpha should not be granted a second WAP.
        $noWap = factory(Person::class)->create([ 'status' => 'alpha', 'callsign' => 'No Wap' ]);
        factory(AccessDocument::class)->create([
               'source_year' => date('Y'),
               'person_id'   => $noWap->id,
               'type'        => 'work_access_pass',
               'status'      => 'claimed'
           ]);

        $prospective = factory(Person::class)->create([ 'status' => 'prospective', 'callsign' => 'B' ]);
        $slot = factory(Slot::class)->create([
               'position_id' => Position::TRAINING,
               'begins'  => date('Y-12-31 23:00:00'),
               'ends'    => date('Y-12-31 23:00:01'),
               'max' => 2,
               'description' => 'A Training'
           ]);
        factory(PersonSlot::class)->create([ 'person_id' => $prospective->id, 'slot_id' => $slot->id ]);

        $response = $this->json('POST', 'access-document/grant-alpha-waps');
        $response->assertStatus(200);
        $response->assertJsonCount(2, 'people.*.id');

        $response->assertJson([
               'people' => [
                   [
                       'id'  => $alpha->id,
                       'callsign' => $alpha->callsign,
                   ],
                   [
                       'id' => $prospective->id,
                       'callsign' => $prospective->callsign
                   ]
               ]
           ]);

        $this->assertDatabaseHas('access_document', [
               'person_id'   => $alpha->id,
               'type'    => 'work_access_pass'
           ]);

        $this->assertDatabaseHas('access_document', [
               'person_id'   => $prospective->id,
               'type'    => 'work_access_pass'
           ]);

        // noWap person should not have been issued a second wap.
        $this->assertEquals(AccessDocument::where('person_id', $noWap->id)->count(), 1);
    }

    /*
     * Test granting a vehicle pass to folks who have tickets granted
     */

    public function testGrantVehiclePass()
    {
        $this->addAdminRole();

        // Person who should be granted a VP
        $person = factory(Person::class)->create();
        $t = factory(AccessDocument::class)->create([
                'person_id'   => $person->id,
                'source_year' => date('Y'),
                'type'        => 'reduced_price_ticket',
                'status'      => 'qualified'
            ]);


        // Person should not be granted a (second) VP
        $noVP = factory(Person::class)->create();
        factory(AccessDocument::class)->create([
                'person_id'   => $noVP->id,
                'source_year' => date('Y'),
                'type'        => 'reduced_price_ticket',
                'status'      => 'qualified'
            ]);

        factory(AccessDocument::class)->create([
                'person_id'   => $noVP->id,
                'source_year' => date('Y'),
                'type'        => 'vehicle_pass',
                'status'      => 'qualified'
            ]);

        $response = $this->json('POST', 'access-document/grant-vps');
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'people.*.id');
        $response->assertJson([
                'people' => [
                    [
                        'id' => $person->id,
                        'callsign' => $person->callsign
                    ]
                ]
            ]);
        $this->assertDatabaseHas('access_document', [
                'person_id' => $person->id,
                'type'  => 'vehicle_pass'
            ]);

        $this->assertEquals(AccessDocument::where('person_id', $noVP->id)->where('type', 'vehicle_pass')->count(), 1);
    }

    /*
     * Test setting the date on Staff Credentials with unspecified access dates
     */

    public function testSetStaffCredentialDates()
    {
        $this->addAdminRole();

        $accessDate = date('Y-08-20');

        $this->setting('TAS_DefaultWAPDate', $accessDate);

        $person = factory(Person::class)->create();
        $setSC = factory(AccessDocument::class)->create([
             'person_id' => $person->id,
             'type' => 'staff_credential',
             'status' => 'qualified',
             'access_date'  => null,
             'access_any_time' => false,
         ]);
        $ignoreSC = factory(AccessDocument::class)->create([
             'person_id' => $person->id,
             'type' => 'staff_credential',
             'status' => 'qualified',
             'access_date'  => null,
             'access_any_time' => true,
         ]);

        $response = $this->json('POST', 'access-document/set-staff-credentials-access-date');
        $response->assertStatus(200);

        $response->assertJson([ 'access_date' => $accessDate ]);
        $response->assertJsonCount(1, 'access_documents.*.id');
        $response->assertJson([
             'access_documents' => [ [ 'id'   => $setSC->id ] ]
         ]);

        $this->assertDatabaseHas('access_document', [
             'id' => $setSC->id,
             'access_date' => $accessDate,
         ]);

        $this->assertDatabaseHas('access_document', [
             'id' => $ignoreSC->id,
             'access_date' => null,
             'access_any_time' => true
         ]);
    }

    /*
     * Test Clean Access Documents from prior event. Mark non-bankable unclaimed docs as expired,
     * and submitted documents as used.
     */

    public function testCleanAccessDocumentsFromPriorEvent()
    {
        $this->addAdminRole();

        $year = date('Y');

        $person = factory(Person::class)->create();
        $qualified = factory(AccessDocument::class)->create([
              'person_id'   => $person->id,
              'source_year' => $year,
              'type' => 'vehicle_pass',
              'status' => 'qualified',
          ]);

        $submitted = factory(AccessDocument::class)->create([
              'person_id'   => $person->id,
              'source_year' => $year,
              'type' => 'staff_credential',
              'status' => 'submitted',
          ]);

        $banked = factory(AccessDocument::class)->create([
              'person_id'   => $person->id,
              'source_year' => $year,
              'type' => 'reduced_price_ticket',
              'status' => 'banked',
          ]);

        $response = $this->json('POST', 'access-document/clean-access-documents');
        $response->assertStatus(200);

        $response->assertJsonCount(2, 'access_documents.*.id');
        $response->assertJson([ 'access_documents' => [
                [
                    'id'    => $qualified->id,
                    'status' => 'expired'
                ],
                [
                    'id'    => $submitted->id,
                    'status'    => 'used'
                ]
        ]]);

        $this->assertDatabaseHas('access_document', [ 'id' => $qualified->id, 'status' => 'expired' ]);
        $this->assertDatabaseHas('access_document', [ 'id' => $submitted->id, 'status' => 'used' ]);
        $this->assertDatabaseHas('access_document', [ 'id' => $banked->id, 'status' => 'banked' ]);
    }

    /*
     * Test banking access documents.
     */

    public function testBankAccessDocuments()
    {
        $this->addAdminRole();

        $year = date('Y');

        $person = factory(Person::class)->create();
        $qualified = factory(AccessDocument::class)->create([
               'person_id'   => $person->id,
               'source_year' => $year,
               'type'        => 'staff_credential',
               'status'      => 'qualified',
           ]);

        // Should not bank this.
        $vp = factory(AccessDocument::class)->create([
               'person_id'   => $person->id,
               'source_year' => $year,
               'type'        => 'vehicle_pass',
               'status'      => 'qualified',
           ]);

        $response = $this->json('POST', 'access-document/bank-access-documents');
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'access_documents.*.id');
        $response->assertJson([
              'access_documents' => [ [ 'id' => $qualified->id, 'status' => 'banked' ]
              ]
          ]);

        $this->assertDatabaseHas('access_document', [ 'id' => $qualified->id, 'status' => 'banked' ]);
        $this->assertDatabaseHas('access_document', [ 'id' => $vp->id, 'status' => 'qualified' ]);
    }

    /*
     * Test expiring access documents
     */

    public function testExpireAccessDocuments()
    {
        $this->addAdminRole();

        $year = date('Y');
        $lastYear = $year - 1;
        $nextYear = $year + 1;

        $person = factory(Person::class)->create();
        $expire = factory(AccessDocument::class)->create([
                'person_id'   => $person->id,
                'source_year' => $year,
                'type'        => 'staff_credential',
                'status'      => 'qualified',
                'expiry_date'   => "$lastYear-08-20"
            ]);

        // Should not bank this.
        $ignore = factory(AccessDocument::class)->create([
                'person_id'   => $person->id,
                'source_year' => $year,
                'type'        => 'staff_credential',
                'status'      => 'qualified',
                'expiry_date' => "$nextYear-08-20"
            ]);

        $response = $this->json('POST', 'access-document/expire-access-documents');
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'access_documents.*.id');
        $response->assertJson([
            'access_documents' => [
                [
                    'id' => $expire->id,
                    'status' => 'expired',
                    'person' => [
                        'id' => $person->id,
                        'callsign' => $person->callsign
                    ]
                ]
            ]
        ]);

        $this->assertDatabaseHas('access_document', [ 'id' => $expire->id, 'status' => 'expired' ]);
        $this->assertDatabaseHas('access_document', [ 'id' => $ignore->id, 'status' => 'qualified' ]);
    }
}
