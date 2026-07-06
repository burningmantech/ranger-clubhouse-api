<?php

namespace Tests\Feature;

use App\Models\AccessDocument;
use App\Models\Person;
use App\Models\PersonSlot;
use App\Models\Position;
use App\Models\Slot;
use App\Models\Timesheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AccessDocumentControllerTest extends TestCase
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
    }

    private function createAccessDocument()
    {
        return AccessDocument::factory()->create([
            'type' => AccessDocument::STAFF_CREDENTIAL,
            'status' => AccessDocument::QUALIFIED,
            'person_id' => $this->user->id,
            'source_year' => (int)date('Y'),
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
                'id' => $ad->id,
                'type' => $ad->type,
                'status' => $ad->status,
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
            'person_id' => $this->user->id,
            'type' => AccessDocument::STAFF_CREDENTIAL,
            'status' => AccessDocument::QUALIFIED,
            'source_year' => date('Y'),
            'expiry_date' => date('Y-12-31'),
        ];

        $response = $this->json('POST', 'access-document', ['access_document' => $data]);
        $response->assertStatus(200);
        $this->assertDatabaseHas('access_document', $data);
    }

    /*
     * Update an access Document for allowed user (non-protected field)
     */

    public function testAccessDocumentUpdateSuccess()
    {
        $ad = AccessDocument::factory()->create([
            'type' => AccessDocument::SPT,
            'status' => AccessDocument::CLAIMED,
            'person_id' => $this->user->id,
            'source_year' => current_year(),
            'delivery_method' => AccessDocument::DELIVERY_NONE,
        ]);

        $response = $this->json('PUT', "access-document/{$ad->id}", ['access_document' => [
            'delivery_method' => AccessDocument::DELIVERY_POSTAL
        ]]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('access_document', [
            'id' => $ad->id,
            'delivery_method' => AccessDocument::DELIVERY_POSTAL
        ]);
    }

    /*
     * An admin may change a protected field (status) via update().
     */

    public function testAccessDocumentUpdateStatusAsAdminSuccess()
    {
        $this->addAdminRole();
        $ad = $this->createAccessDocument();

        $response = $this->json('PUT', "access-document/{$ad->id}", ['access_document' => [
            'status' => AccessDocument::BANKED
        ]]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('access_document', [
            'id' => $ad->id,
            'status' => AccessDocument::BANKED
        ]);
    }

    /*
     * A non-admin owner must NOT be able to escalate privileges by changing protected
     * fields (status, type, person_id, source_year) via update().
     */

    public function testAccessDocumentUpdateProtectedFieldsForbidden()
    {
        $other = Person::factory()->create();

        foreach ([
            'status' => AccessDocument::CLAIMED,
            'type' => AccessDocument::GIFT,
            'person_id' => $other->id,
            'source_year' => current_year() + 1,
            'expiry_date' => (current_year() + 5) . '-09-15',
        ] as $field => $value) {
            $ad = $this->createAccessDocument();

            $response = $this->json('PUT', "access-document/{$ad->id}", ['access_document' => [
                $field => $value
            ]]);

            $response->assertStatus(403);

            $ad->refresh();
            $this->assertEquals(AccessDocument::STAFF_CREDENTIAL, $ad->type);
            $this->assertEquals(AccessDocument::QUALIFIED, $ad->status);
            $this->assertEquals($this->user->id, $ad->person_id);
            $this->assertEquals(current_year(), $ad->source_year);
        }
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
        $this->assertDatabaseMissing('access_document', ['id' => $ad->id]);
    }

    /*
     * Test changing the status on an access document
     */

    public function testStatusChangeSuccess()
    {
        $ad = $this->createAccessDocument();

        $response = $this->json('PATCH', "access-document/{$ad->id}/status", ['status' => AccessDocument::BANKED]);
        $response->assertStatus(200);
        $this->assertDatabaseHas('access_document', ['id' => $ad->id, 'status' => AccessDocument::BANKED]);
    }

    /*
     * Test retrieving the current access documents
     */

    public function testCurrentAccessDocumentSuccess()
    {
        $this->addAdminRole();

        $this->setting('TAS_SAPDateRange', '3-24');

        $ad = $this->createAccessDocument();

        $response = $this->json('GET', 'access-document/current');
        $response->assertStatus(200);
        $response->assertJson([
            'documents' => [
                'people' => [
                    [
                        'person' => [
                            'id' => $this->user->id,
                            'callsign' => $this->user->callsign
                        ],

                        'documents' => [
                            [
                                'id' => $ad->id,
                                'type' => $ad->type
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

        $expiring = AccessDocument::factory()->create([
            'person_id' => $this->user->id,
            'type' => AccessDocument::STAFF_CREDENTIAL,
            'status' => AccessDocument::QUALIFIED,
            'source_year' => date('Y'),
            'expiry_date' => date("Y-12-31"),
        ]);

        $response = $this->json('GET', 'access-document/expiring');
        $response->assertStatus(200);
        $response->assertJson([
            'expiring' => [
                [
                    'person' => [
                        'id' => $this->user->id,
                        'callsign' => $this->user->callsign
                    ],
                    'tickets' => [
                        [
                            'id' => $expiring->id,
                        ]
                    ]
                ]
            ]
        ]);
    }

    /*
     * Test marking a batch of Access Documents as submitted.
     */

    public function testMarkSubmittedSuccess()
    {
        $this->addAdminRole();

        $ad = $this->createAccessDocument();
        $ad->update(['status' => 'claimed']);

        $response = $this->json('POST', 'access-document/mark-submitted', ['ids' => [$ad->id]]);
        $response->assertStatus(200);
        $this->assertDatabaseHas('access_document', ['id' => $ad->id, 'status' => 'submitted']);
    }

    /*
     * Test Grant WAPs
     */

    public function testGrantWAPs()
    {
        $this->addAdminRole();

        $this->setting('TAS_DefaultWAPDate', date('Y-08-20'));

        $year = date('Y') - 2;
        $slot = Slot::factory()->create([
            'position_id' => Position::TRAINING,
            'begins' => date('Y-04-01 10:00:00'),
            'ends' => date('Y-04-01 12:00:00'),
            'max' => 2,
            'description' => 'A Training'
        ]);


        $active = Person::factory()->create(['callsign' => 'A']);
        $timesheet = Timesheet::factory()->create([
            'person_id' => $active->id,
            'position_id' => Position::DIRT,
            'on_duty' => date("$year-08-25 12:00:00"),
            'off_duty' => date("$year-08-25 13:00:00")
        ]);

        PersonSlot::factory()->create(['person_id' => $active->id, 'slot_id' => $slot->id]);

        // Person should not be granted a second WAP.
        $noWap = Person::factory()->create(['callsign' => 'No Wap']);
        $noWapTimesheet = Timesheet::factory()->create([
            'person_id' => $noWap->id,
            'position_id' => Position::DIRT,
            'on_duty' => date("$year-08-25 12:00:00"),
            'off_duty' => date("$year-08-25 13:00:00")
        ]);
        AccessDocument::factory()->create([
            'source_year' => date('Y'),
            'person_id' => $noWap->id,
            'type' => AccessDocument::WAP,
            'status' => AccessDocument::QUALIFIED
        ]);

        $retired = Person::factory()->create(['status' => 'retired', 'callsign' => 'B']);
        PersonSlot::factory()->create(['person_id' => $retired->id, 'slot_id' => $slot->id]);

        $response = $this->json('POST', 'access-document/grant-waps');
        $response->assertStatus(200);

        //$response->assertJsonCount(2, 'people.*.id');
        $response->assertJson([
            'people' => [
                [
                    'id' => $active->id,
                    'callsign' => $active->callsign,
                ],
                [
                    'id' => $retired->id,
                    'callsign' => $retired->callsign
                ]
            ]
        ]);

        $this->assertDatabaseHas('access_document', [
            'person_id' => $active->id,
            'type' => 'work_access_pass'
        ]);

        $this->assertDatabaseHas('access_document', [
            'person_id' => $retired->id,
            'type' => 'work_access_pass'
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
        $alpha = Person::factory()->create(['status' => Person::ALPHA, 'callsign' => 'Alpha 1']);

        // Alpha should not be granted a second WAP.
        $noWap = Person::factory()->create(['status' => Person::ALPHA]);
        AccessDocument::factory()->create([
            'source_year' => date('Y'),
            'person_id' => $noWap->id,
            'type' => 'work_access_pass',
            'status' => 'claimed'
        ]);

        $prospective = Person::factory()->create(['status' => Person::PROSPECTIVE, 'callsign' => 'Prospective 1']);
        $slot = Slot::factory()->create([
            'position_id' => Position::TRAINING,
            'begins' => date('Y-12-31 23:00:00'),
            'ends' => date('Y-12-31 23:00:01'),
            'max' => 2,
            'description' => 'A Training'
        ]);
        PersonSlot::factory()->create(['person_id' => $prospective->id, 'slot_id' => $slot->id]);

        $response = $this->json('POST', 'access-document/grant-alpha-waps');
        $response->assertStatus(200);
        $response->assertJsonCount(2, 'people.*.id');

        $response->assertJson([
            'people' => [
                [
                    'id' => $alpha->id,
                    'callsign' => $alpha->callsign,
                ],
                [
                    'id' => $prospective->id,
                    'callsign' => $prospective->callsign,
                    'status' => Person::PROSPECTIVE,
                ]
            ]
        ]);

        $this->assertDatabaseHas('access_document', [
            'person_id' => $alpha->id,
            'type' => 'work_access_pass'
        ]);

        $this->assertDatabaseHas('access_document', [
            'person_id' => $prospective->id,
            'type' => 'work_access_pass'
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
        $person = Person::factory()->create();
        $t = AccessDocument::factory()->create([
            'person_id' => $person->id,
            'source_year' => date('Y'),
            'type' => AccessDocument::SPT,
            'status' => AccessDocument::QUALIFIED
        ]);


        // Person should not be granted a (second) VP
        $noVP = Person::factory()->create();
        AccessDocument::factory()->create([
            'person_id' => $noVP->id,
            'source_year' => date('Y'),
            'type' => AccessDocument::SPT,
            'status' => AccessDocument::QUALIFIED
        ]);

        AccessDocument::factory()->create([
            'person_id' => $noVP->id,
            'source_year' => date('Y'),
            'type' => AccessDocument::VEHICLE_PASS_SP,
            'status' => AccessDocument::QUALIFIED
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
            'type' => AccessDocument::VEHICLE_PASS_SP,
        ]);

        $this->assertEquals(1, AccessDocument::where('person_id', $noVP->id)->where('type', AccessDocument::VEHICLE_PASS_SP)->count());
    }

    /*
     * Test setting the date on Staff Credentials with unspecified access dates
     */

    public function testSetStaffCredentialDates()
    {
        $this->addAdminRole();

        $accessDate = date('Y-08-20');

        $this->setting('TAS_DefaultWAPDate', $accessDate);

        $person = Person::factory()->create();
        $setSC = AccessDocument::factory()->create([
            'person_id' => $person->id,
            'type' => AccessDocument::STAFF_CREDENTIAL,
            'status' => AccessDocument::QUALIFIED,
            'access_date' => null,
            'access_any_time' => false,
        ]);
        $ignoreSC = AccessDocument::factory()->create([
            'person_id' => $person->id,
            'type' => AccessDocument::STAFF_CREDENTIAL,
            'status' => AccessDocument::QUALIFIED,
            'access_date' => null,
            'access_any_time' => true,
        ]);

        $response = $this->json('POST', 'access-document/set-staff-credentials-access-date');
        $response->assertStatus(200);

        $response->assertJson(['access_date' => $accessDate]);
        $response->assertJsonCount(1, 'access_documents.*.id');
        $response->assertJson([
            'access_documents' => [['id' => $setSC->id]]
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

        $person = Person::factory()->create();
        $qualified = AccessDocument::factory()->create([
            'person_id' => $person->id,
            'source_year' => $year,
            'type' => AccessDocument::VEHICLE_PASS_GIFT,
            'status' => AccessDocument::QUALIFIED,
        ]);

        $submitted = AccessDocument::factory()->create([
            'person_id' => $person->id,
            'source_year' => $year,
            'type' => AccessDocument::STAFF_CREDENTIAL,
            'status' => 'submitted',
        ]);

        $banked = AccessDocument::factory()->create([
            'person_id' => $person->id,
            'source_year' => $year,
            'type' => AccessDocument::SPT,
            'status' => AccessDocument::BANKED,
        ]);

        $response = $this->json('POST', 'access-document/clean-access-documents');
        $response->assertStatus(200);

        $response->assertJsonCount(2, 'access_documents.*.id');
        $response->assertJson(['access_documents' => [
            [
                'id' => $qualified->id,
                'status' => AccessDocument::EXPIRED
            ],
            [
                'id' => $submitted->id,
                'status' => AccessDocument::USED
            ]
        ]]);

        $this->assertDatabaseHas('access_document', ['id' => $qualified->id, 'status' => AccessDocument::EXPIRED]);
        $this->assertDatabaseHas('access_document', ['id' => $submitted->id, 'status' => AccessDocument::USED]);
        $this->assertDatabaseHas('access_document', ['id' => $banked->id, 'status' => AccessDocument::BANKED]);
    }

    /*
     * Test banking access documents.
     */

    public function testBankAccessDocuments()
    {
        $this->addAdminRole();

        $year = date('Y');

        $person = Person::factory()->create();
        $qualified = AccessDocument::factory()->create([
            'person_id' => $person->id,
            'source_year' => $year,
            'type' => AccessDocument::STAFF_CREDENTIAL,
            'status' => AccessDocument::QUALIFIED,
        ]);

        // Should not bank this.
        $vp = AccessDocument::factory()->create([
            'person_id' => $person->id,
            'source_year' => $year,
            'type' => AccessDocument::VEHICLE_PASS_GIFT,
            'status' => AccessDocument::QUALIFIED,
        ]);

        $response = $this->json('POST', 'access-document/bank-access-documents');

        $response->assertStatus(200);
        $response->assertJson([
            'access_documents' => [
                ['id' => $qualified->id, 'status' => AccessDocument::BANKED]
            ]
        ]);

        $response->assertJsonCount(1, 'access_documents.*.id');
        $this->assertDatabaseHas('access_document', ['id' => $qualified->id, 'status' => AccessDocument::BANKED]);
        $this->assertDatabaseHas('access_document', ['id' => $vp->id, 'status' => AccessDocument::QUALIFIED]);
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

        $person = Person::factory()->create();
        $expire = AccessDocument::factory()->create([
            'person_id' => $person->id,
            'source_year' => $year,
            'type' => AccessDocument::STAFF_CREDENTIAL,
            'status' => AccessDocument::QUALIFIED,
            'expiry_date' => "$lastYear-08-20"
        ]);

        // Should not bank this.
        $ignore = AccessDocument::factory()->create([
            'person_id' => $person->id,
            'source_year' => $year,
            'type' => AccessDocument::STAFF_CREDENTIAL,
            'status' => AccessDocument::QUALIFIED,
            'expiry_date' => "$nextYear-08-20"
        ]);

        $response = $this->json('POST', 'access-document/expire-access-documents');
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'access_documents.*.id');
        $response->assertJson([
            'access_documents' => [
                [
                    'id' => $expire->id,
                    'status' => AccessDocument::EXPIRED,
                    'person' => [
                        'id' => $person->id,
                        'callsign' => $person->callsign
                    ]
                ]
            ]
        ]);

        $this->assertDatabaseHas('access_document', ['id' => $expire->id, 'status' => AccessDocument::EXPIRED]);
        $this->assertDatabaseHas('access_document', ['id' => $ignore->id, 'status' => AccessDocument::QUALIFIED]);
    }

    /*
     * Test bump expiration
     */

    public function testBumpTicketExpiration()
    {
        $this->addAdminRole();
        $person = Person::factory()->create();
        $year = current_year();
        $expireYear = $year + 3;
        $ad = AccessDocument::factory()->create([
            'person_id' => $person->id,
            'source_year' => $year,
            'type' => AccessDocument::STAFF_CREDENTIAL,
            'status' => AccessDocument::QUALIFIED,
            'expiry_date' => "$expireYear-09-15"
        ]);

        $response = $this->json('POST', 'access-document/bump-expiration');
        $response->assertStatus(200);
        $response->assertJson(['count' => 1]);

        $ad->refresh();
        $expireYear++;
        $this->assertEquals("$expireYear-09-15 00:00:00", (string)$ad->expiry_date);
    }

    /*
     * bumpExpiration must ignore expire-this-year types (WAP / vehicle passes), whose expiry the
     * saving() hook force-resets, and must not crash on a null expiry_date. The reported count
     * should reflect only the rows actually bumped.
     */

    public function testBumpTicketExpirationIgnoresExpireThisYearAndNullExpiry()
    {
        $this->addAdminRole();
        $person = Person::factory()->create();
        $year = current_year();
        $expireYear = $year + 3;

        // Regular ticket - should be bumped.
        $sc = AccessDocument::factory()->create([
            'person_id' => $person->id,
            'source_year' => $year,
            'type' => AccessDocument::STAFF_CREDENTIAL,
            'status' => AccessDocument::QUALIFIED,
            'expiry_date' => "$expireYear-09-15"
        ]);

        // WAP - expire-this-year type, must be ignored (would be reverted to current year).
        $wap = AccessDocument::factory()->create([
            'person_id' => $person->id,
            'source_year' => $year,
            'type' => AccessDocument::WAP,
            'status' => AccessDocument::QUALIFIED,
        ]);

        // Vehicle pass - expire-this-year type, must be ignored.
        $vp = AccessDocument::factory()->create([
            'person_id' => $person->id,
            'source_year' => $year,
            'type' => AccessDocument::VEHICLE_PASS_SP,
            'status' => AccessDocument::QUALIFIED,
        ]);

        // Regular ticket with a null expiry_date - must not crash and must not be bumped.
        $nullExpiry = AccessDocument::factory()->create([
            'person_id' => $person->id,
            'source_year' => $year,
            'type' => AccessDocument::SPT,
            'status' => AccessDocument::BANKED,
            'expiry_date' => null,
        ]);

        $response = $this->json('POST', 'access-document/bump-expiration');
        $response->assertStatus(200);
        $response->assertJson(['count' => 1]);

        $sc->refresh();
        $bumped = $expireYear + 1;
        $this->assertEquals("$bumped-09-15 00:00:00", (string)$sc->expiry_date);

        // WAP & VP expiry force-reset to current year by the saving() hook, never bumped past it.
        $wap->refresh();
        $this->assertEquals($year, $wap->expiry_date->year);
        $vp->refresh();
        $this->assertEquals($year, $vp->expiry_date->year);

        // Null-expiry row untouched.
        $nullExpiry->refresh();
        $this->assertNull($nullExpiry->expiry_date);
    }

    /*
     * Deceased or dismissed ticket holders must be excluded entirely from the delivery payload.
     */

    public function testCurrentForDeliveryExcludesDeceasedAndDismissed()
    {
        $this->addAdminRole();

        $alive = Person::factory()->create(['callsign' => 'Alive']);
        $deceased = Person::factory()->create(['callsign' => 'Dead', 'status' => Person::DECEASED]);
        $dismissed = Person::factory()->create(['callsign' => 'Gone', 'status' => Person::DISMISSED]);

        foreach ([$alive, $deceased, $dismissed] as $person) {
            AccessDocument::factory()->create([
                'person_id' => $person->id,
                'source_year' => current_year(),
                'type' => AccessDocument::SPT,
                'status' => AccessDocument::CLAIMED,
                'delivery_method' => AccessDocument::DELIVERY_POSTAL,
            ]);
        }

        $response = $this->json('GET', 'access-document/current', ['for_delivery' => 1]);
        $response->assertStatus(200);

        $ids = collect($response->json('documents.people'))->pluck('person.id')->all();
        $this->assertContains($alive->id, $ids);
        $this->assertNotContains($deceased->id, $ids);
        $this->assertNotContains($dismissed->id, $ids);
    }
}
