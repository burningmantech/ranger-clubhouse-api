<?php

namespace Tests\Feature;

use App\Lib\EventreeBMIDExport;
use App\Models\AccessDocument;
use App\Models\Bmid;
use App\Models\BmidExport;
use App\Models\Person;
use App\Models\PersonEvent;
use App\Models\PersonPhoto;
use App\Models\PersonPosition;
use App\Models\PersonSlot;
use App\Models\Position;
use App\Models\Provision;
use App\Models\Role;
use App\Models\Slot;
use App\Models\TraineeStatus;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BmidControllerTest extends TestCase
{
    use RefreshDatabase;

    public $year;
    public $people;

    /*
     * have each test have a fresh user that is logged in.
     */

    public function setUp(): void
    {
        parent::setUp();

        $this->signInUser();
        $this->addRole(Role::EDIT_BMIDS);

        $this->year = current_year();

        $this->setting('TAS_BoxOfficeOpenDate', "{$this->year}-08-25 00:00");
        $this->setting('TAS_DefaultWAPDate', date('Y-08-26'));
    }

    public function createBmids()
    {
        $year = $this->year;

        /*
         * Create special BMIDS -
         *  - has a title
         *  - has a meal
         *  - has a shower
         *  - has WAP access date before the box office opens.
         */

        $personWithTitle = Person::factory()->create(['callsign' => 'Masters McTitle']);
        Bmid::factory()->create(['person_id' => $personWithTitle->id, 'title1' => 'hasTitle', 'year' => $year]);

        $personWithMeal = Person::factory()->create(['callsign' => 'Death Eater']);
        Bmid::factory()->create(['person_id' => $personWithMeal->id, 'meals' => 'all', 'year' => $year]);

        $personWithShower = Person::factory()->create(['callsign' => 'Wet Spot Willie']);
        Bmid::factory()->create(['person_id' => $personWithShower->id, 'showers' => true, 'year' => $year]);

        $personWithWap = Person::factory()->create(['callsign' => 'Early Bird']);
        $wap = AccessDocument::factory()->create([
            'person_id' => $personWithWap->id,
            'access_date' => "$year-08-20 00:00:00",
            'type' => AccessDocument::WAP,
            'status' => 'claimed',
        ]);

        // create an alpha
        $personAlpha = Person::factory()->create(['callsign' => 'Alpha Beta', 'status' => 'alpha']);

        // Person signed up for on playa shifts, and/or passed training
        $slot = Slot::factory()->create([
            'begins' => "$year-08-20 00:00:00",
            'ends' => "$year-08-20 06:00:00",
            'position_id' => Position::DIRT,
        ]);
        $personPlaya = Person::factory()->create(['callsign' => 'Dusty Bottoms']);
        PersonSlot::factory()->create(['person_id' => $personPlaya->id, 'slot_id' => $slot->id]);

        // Vet passed training
        $trainingSlot = Slot::factory()->create([
            'begins' => "$year-07-20 09:45:00",
            'ends' => "$year-07-20 17:45:00",
            'position_id' => Position::TRAINING,
        ]);

        Position::factory()->create([
            'id' => Position::TRAINING,
            'type' => 'Training'
        ]);

        $personTrained = Person::factory()->create(['callsign' => 'Trenton Trained']);

        TraineeStatus::factory()->create([
            'slot_id' => $trainingSlot->id,
            'person_id' => $personTrained->id,
            'passed' => true,
        ]);

        $this->people = [
            'title' => $personWithTitle,
            'meal' => $personWithMeal,
            'shower' => $personWithShower,
            'wap' => $personWithWap,
            'alpha' => $personAlpha,
            'onplaya' => $personPlaya,
            'trained' => $personTrained,
        ];

        // Processed BMIDs
        foreach (['submitted', 'issues'] as $status) {
            $person = Person::factory()->create(['callsign' => "BMID $status"]);
            Bmid::factory()->create([
                'person_id' => $person->id,
                'year' => $year,
                'status' => $status,
            ]);

            $this->people[$status] = $person;
        }
    }

    /*
     * Find (raw) BMIDs for query
     */

    public function testBmidIndex()
    {
        $person = Person::factory()->create();
        $year = current_year();

        $bmid = Bmid::factory()->create([
            'person_id' => $person->id,
            'year' => $year,
            'title1' => 'Title X',
            'title2' => 'Title Y',
            'title3' => 'Title Z'
        ]);

        $response = $this->json('GET', 'bmid', ['year' => $year]);
        $response->assertStatus(200);
        $response->assertJson([
            'bmids' => [
                [
                    'person_id' => $person->id,
                    'year' => $year,
                    'title1' => 'Title X',
                    'title2' => 'Title Y',
                    'title3' => 'Title Z'
                ]
            ]
        ]);
    }

    /*
     * Find signed up BMIDs for management
     */

    public function testFindBmidsForSignedup()
    {
        $this->createBmids();

        $response = $this->json('GET', 'bmid/manage', ['year' => $this->year, 'filter' => 'signedup']);
        $response->assertStatus(200);

        $response->assertJson(
            [
                'bmids' => [
                    [
                        'person_id' => $this->people['onplaya']->id,
                        'status' => 'in_prep'
                    ],
                    [
                        'person_id' => $this->people['trained']->id,
                        'status' => 'in_prep'
                    ]
                ]]
        );

        $response->assertJsonCount(2, 'bmids.*.person_id');
    }

    /*
     * Find everyone who has a BMID with:
     *   - a title
     *   - showers
     *   - meals
     * OR anyone who has a WAP opening before the box office.
     */

    public function testFindBmidsForSpecial()
    {
        $this->createBmids();

        $response = $this->json('GET', 'bmid/manage', ['year' => $this->year, 'filter' => 'special']);
        $response->assertStatus(200);

        $response->assertJsonFragment(['person_id' => $this->people['title']->id]);
        $response->assertJsonFragment(['person_id' => $this->people['meal']->id]);
        $response->assertJsonFragment(['person_id' => $this->people['shower']->id]);
        $response->assertJsonFragment(['person_id' => $this->people['wap']->id]);
        $response->assertJsonCount(4, 'bmids.*.person_id');
    }

    /*
     * Find everyone who has a submitted BMID
     */

    public function testFindBmidsSubmitted()
    {
        $this->createBmids();

        $response = $this->json('GET', 'bmid/manage', ['year' => $this->year, 'filter' => 'submitted']);
        $response->assertStatus(200);

        $response->assertJsonFragment(['person_id' => $this->people['submitted']->id]);
        $response->assertJsonCount(1, 'bmids.*.person_id');
    }


    /*
     * Find folks with ... issues.
     */

    public function testFindBmidsWithIssues()
    {
        $this->createBmids();

        $response = $this->json('GET', 'bmid/manage', ['year' => $this->year, 'filter' => 'nonprint']);
        $response->assertStatus(200);

        $response->assertJsonFragment(['person_id' => $this->people['issues']->id]);
        $response->assertJsonCount(1, 'bmids.*.person_id');
    }

    /*
     * create a BMID
     */

    public function testCreateBmid()
    {
        $person = Person::factory()->create();
        $data = [
            'year' => $this->year,
            'person_id' => $person->id,
            'title1' => 'Lord Of The Flies',
            'title2' => 'Head Supreme',
            'title3' => 'Keeper of Puns',
        ];

        $response = $this->json('POST', 'bmid', ['bmid' => $data]);
        $response->assertStatus(200);
        $response->assertJson(['bmid' => $data]);
        $this->assertDatabaseHas('bmid', $data);
    }

    /*
     * Update a BMID
     */

    public function testUpdateBmid()
    {
        $data = [
            'year' => $this->year,
            'person_id' => $this->user->id,
            'title1' => 'Village Idiot',
        ];

        $bmid = Bmid::factory()->create($data);

        $response = $this->json('PUT', "bmid/{$bmid->id}", ['bmid' => ['title1' => 'Town Crier']]);
        $response->assertStatus(200);
        $this->assertDatabaseHas('bmid', [
            'id' => $bmid->id,
            'title1' => 'Town Crier'
        ]);
    }

    /*
     * Delete a BMID
     */

    public function testDestroyBmid()
    {
        $data = [
            'year' => $this->year,
            'person_id' => $this->user->id,
        ];

        $bmid = Bmid::factory()->create($data);

        $response = $this->json('DELETE', "bmid/{$bmid->id}");
        $response->assertStatus(204);
        $this->assertDatabaseMissing('bmid', [
            'id' => $bmid->id,
        ]);
    }

    /*
     * Find a potential BMID to manage
     */

    public function testFindPotentialBmidToManage()
    {
        $person = Person::factory()->create();

        Provision::factory()->create([
            'person_id' => $person->id,
            'type' => Provision::MEALS,
            'pre_event_meals' => false,
            'event_week_meals' => true,
            'post_event_meals' => false,
            'status' => Provision::CLAIMED,
            'source_year' => $this->year,
            'expires_on' => $this->year
        ]);

        Provision::factory()->create([
            'person_id' => $person->id,
            'type' => Provision::WET_SPOT,
            'status' => Provision::CLAIMED,
            'source_year' => $this->year,
            'expires_on' => $this->year
        ]);

        $response = $this->json(
            'GET',
            'bmid/manage-person',
            ['year' => $this->year, 'person_id' => $person->id]
        );


        $response->assertStatus(200);
        $response->assertJson([
            'bmid' => [
                'person_id' => $person->id,
                'year' => $this->year,
                'showers_granted' => true,
                'meals_granted' => [
                    'pre' => false,
                    'event' => true,
                    'post' => false,
                ]
            ]
        ]);

        $json = json_decode($response->getContent(), true);
        $this->assertArrayNotHasKey('id', $json['bmid']);
    }

    /*
     * Find an existing BMID to manage
     */

    public function testFindExistingBmidToManage()
    {
        $person = Person::factory()->create();
        $bmid = Bmid::factory()->create(['person_id' => $person->id, 'year' => $this->year]);

        $response = $this->json(
            'GET',
            'bmid/manage-person',
            ['year' => $this->year, 'person_id' => $person->id]
        );

        $response->assertStatus(200);
        $response->assertJson([
            'bmid' => [
                'id' => $bmid->id,
                'person_id' => $person->id,
                'year' => $this->year,
            ]
        ]);
    }

    /*
     * Do not find any BMIDs
     */

    public function testDoNotFindBmidToManage()
    {
        $response = $this->json(
            'GET',
            'bmid/manage-person',
            ['year' => $this->year, 'person_id' => 999999]
        );

        $response->assertStatus(422);
        $response->assertJson([
            'errors' => [
                ['title' => 'The selected person id is invalid.']
            ]
        ]);
    }

    /*
     * Test setting BMID titles for people who have special positions
     */

    public function testSetBMIDTitlesWithClaimedTicket()
    {
        // No BMID should be created for a person who does not hold any special positions.
        $simple = Person::factory()->create();

        // BMID should be created and one title set with a person who meets the ticket claim criteria
        $ood = Person::factory()->create();
        PersonPosition::factory()->create(['person_id' => $ood->id, 'position_id' => Position::OOD]);
        // Ticket criteria is met.
        AccessDocument::factory()->create([
            'person_id' => $ood->id,
            'type' => AccessDocument::SPT,
            'source_year' => current_year(),
            'status' => AccessDocument::CLAIMED,
        ]);

        $response = $this->json('POST', 'bmid/set-bmid-titles');
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'bmids.*.id');
        $response->assertJson([
            'bmids' => [
                [
                    'id' => $ood->id,
                    'callsign' => $ood->callsign,
                    'title1' => 'Officer of the Day'
                ]
            ]
        ]);

        $this->assertDatabaseHas('bmid', ['person_id' => $ood->id, 'title1' => 'Officer of the Day']);
        $this->assertDatabaseMissing('bmid', ['person_id' => $simple->id]);
    }

    public function testSetBMIDTitlesWithTrainingSignUp()
    {
        // Test for someone who meets the In-Person training criteria
        $shiftLead = Person::factory()->create();
        PersonPosition::factory()->create(['person_id' => $shiftLead->id, 'position_id' => Position::RSC_SHIFT_LEAD]);
        // Ticket criteria is met.
        $slot = Slot::factory()->create([
            'begins' => "{$this->year}-01-01 12:00:00",
            'ends' => "{$this->year}-01-01 12:01:00",
            'position_id' => Position::TRAINING,
        ]);

        PersonSlot::factory()->create([
            'person_id' => $shiftLead->id,
            'slot_id' => $slot->id,
        ]);

        $response = $this->json('POST', 'bmid/set-bmid-titles');
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'bmids.*.id');
        $response->assertJson([
            'bmids' => [
                [
                    'id' => $shiftLead->id,
                    'callsign' => $shiftLead->callsign,
                    'title1' => 'Shift Lead'
                ]
            ]
        ]);

        $this->assertDatabaseHas('bmid', ['person_id' => $shiftLead->id, 'title1' => 'Shift Lead']);
    }

    /**
     * Test no one is given a BMID who has a special position and does not meet the printing criteria
     *
     * @return void
     */

    public function testSetBMIDsTitleNoCriteriaMet()
    {
        // Test for someone who meets the In-Person training criteria
        $shiftLead = Person::factory()->create();
        PersonPosition::factory()->create(['person_id' => $shiftLead->id, 'position_id' => Position::RSC_SHIFT_LEAD]);

        $response = $this->json('POST', 'bmid/set-bmid-titles');
        $response->assertStatus(200);
        $response->assertJsonCount(0, 'bmids.*.id');

        $this->assertDatabaseMissing('bmid', ['person_id' => $shiftLead->id]);
    }

    /**
     * Test exporting to Eventree
     */

    public function testExportEventreeWithPhotos()
    {
        $photoStorage = config('clubhouse.PhotoStorage');
        $exportStorage = config('clubhouse.BmidExportStorage');
        Storage::fake($exportStorage);
        Storage::fake($photoStorage);

        $person = Person::factory()->create();

        Storage::disk($photoStorage)
            ->put(PersonPhoto::storagePath('headshot.jpg'), 'a photo');

        $photo = PersonPhoto::factory()->create([
            'person_id' => $person->id,
            'image_filename' => 'headshot.jpg',
            'status' => PersonPhoto::APPROVED
        ]);

        $person->person_photo_id = $photo->id;
        $person->saveWithoutValidation();

        $bmid = Bmid::factory()->create([
            'year' => $this->year,
            'person_id' => $person->id,
            'title1' => 'Lord Of The Flies',
            'title2' => 'Head Supreme',
            'title3' => 'Keeper of Puns',
            'status' => Bmid::READY_TO_PRINT,
        ]);

        $meals = Provision::factory()->create([
            'person_id' => $person->id,
            'type' => Provision::MEALS,
            'pre_event_meals' => true,
            'event_week_meals' => true,
            'post_event_meals' => true,
            'status' => Provision::CLAIMED,
            'source_year' => $this->year,
            'expires_on' => $this->year
        ]);

        $response = $this->json('POST', 'bmid/export', [
            'year' => $this->year,
            'person_ids' => [$person->id],
            'batch_info' => 'Big Batch',
            'with_photos' => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'bmids' => [[
                'person_id' => $person->id,
                'status' => Bmid::SUBMITTED,
                'batch' => 'Big Batch'
            ]]
        ]);

        $this->assertDatabaseHas('provision', [
            'id' => $meals->id,
            'status' => Provision::SUBMITTED
        ]);

        $this->assertDatabaseHas('bmid_export', ['batch_info' => 'Big Batch']);
        $export = BmidExport::firstOrFail();

        Storage::disk($exportStorage)->assertExists(BmidExport::storagePath($export->filename));
    }

    /**
     * Test exporting to Eventree
     */

    public function testExportEventreeNoPhotos()
    {
        $photoStorage = config('clubhouse.PhotoStorage');
        $exportStorage = config('clubhouse.BmidExportStorage');
        Storage::fake($exportStorage);
        Storage::fake($photoStorage);

        $person = Person::factory()->create();

        $bmid = Bmid::factory()->create([
            'year' => $this->year,
            'person_id' => $person->id,
            'title1' => 'Lord Of The Flies',
            'title2' => 'Head Supreme',
            'title3' => 'Keeper of Puns',
            'status' => Bmid::READY_TO_PRINT,
        ]);

        $meals = Provision::factory()->create([
            'person_id' => $person->id,
            'type' => Provision::MEALS,
            'pre_event_meals' => true,
            'event_week_meals' => true,
            'post_event_meals' => true,
            'status' => Provision::CLAIMED,
            'source_year' => $this->year,
            'expires_on' => $this->year
        ]);

        $response = $this->json('POST', 'bmid/export', [
            'year' => $this->year,
            'person_ids' => [$person->id],
            'batch_info' => 'Big Batch'
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'bmids' => [[
                'person_id' => $person->id,
                'status' => Bmid::SUBMITTED,
                'batch' => 'Big Batch'
            ]]
        ]);

        $this->assertDatabaseHas('provision', [
            'id' => $meals->id,
            'status' => Provision::SUBMITTED
        ]);

        $this->assertDatabaseHas('bmid_export', ['batch_info' => 'Big Batch']);
        $export = BmidExport::firstOrFail();

        Storage::disk($exportStorage)->assertExists(BmidExport::storagePath($export->filename));
    }

    /*
     * store() must return 422 (not 500) when person_id does not reference a real person.
     * Regression: the person_id was not validated with exists:person,id, so a bogus id
     * blew up downstream instead of failing validation.
     */

    public function testStoreReturns422ForNonexistentPerson()
    {
        $response = $this->json('POST', 'bmid', [
            'bmid' => [
                'person_id' => 999999,
                'year' => $this->year,
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'errors' => [
                ['title' => 'The selected bmid.person id is invalid.'],
            ],
        ]);
        $this->assertDatabaseMissing('bmid', ['person_id' => 999999]);
    }

    /*
     * update() must not reassign the badge identity: person_id and year are fixed and
     * must remain unchanged even if the request tries to set different values.
     */

    public function testUpdateCannotChangePersonIdOrYear()
    {
        $owner = Person::factory()->create();
        $other = Person::factory()->create();

        $bmid = Bmid::factory()->create([
            'person_id' => $owner->id,
            'year' => $this->year,
            'title1' => 'Original',
        ]);

        $response = $this->json('PUT', "bmid/{$bmid->id}", [
            'bmid' => [
                'person_id' => $other->id,
                'year' => $this->year + 5,
                'title1' => 'Renamed',
            ],
        ]);

        $response->assertStatus(200);

        $bmid->refresh();
        $this->assertEquals($owner->id, $bmid->person_id);
        $this->assertEquals($this->year, $bmid->year);
        // The mutable field still updated.
        $this->assertEquals('Renamed', $bmid->title1);

        $this->assertDatabaseHas('bmid', [
            'id' => $bmid->id,
            'person_id' => $owner->id,
            'year' => $this->year,
        ]);
        $this->assertDatabaseMissing('bmid', [
            'id' => $bmid->id,
            'person_id' => $other->id,
        ]);
    }

    /*
     * update() must return 422 (not 500) when handed an invalid status value.
     */

    public function testUpdateReturns422ForInvalidStatus()
    {
        $person = Person::factory()->create();
        $bmid = Bmid::factory()->create([
            'person_id' => $person->id,
            'year' => $this->year,
            'status' => Bmid::IN_PREP,
        ]);

        $response = $this->json('PUT', "bmid/{$bmid->id}", [
            'bmid' => ['status' => 'not_a_real_status'],
        ]);

        $response->assertStatus(422);

        $bmid->refresh();
        $this->assertEquals(Bmid::IN_PREP, $bmid->status);
    }

    /*
     * A printable BMID with no access document (no WAP) must export with a BLANK
     * Arrival Date -- not stamped with today's date.
     */

    public function testExportBlanksArrivalDateWhenNoAccessDocument()
    {
        $person = Person::factory()->create();

        $bmid = Bmid::factory()->create([
            'person_id' => $person->id,
            'year' => $this->year,
            'status' => Bmid::READY_TO_PRINT,
        ]);

        // Load relationships the same way the export pipeline does; with no WAP,
        // access_date stays null and access_any_time false.
        Bmid::bulkLoadRelationships(new EloquentCollection([$bmid]), [$bmid->person_id], $bmid->year);

        $this->assertNull($bmid->access_date);
        $this->assertFalse($bmid->access_any_time);

        $row = EventreeBMIDExport::buildExportRow($bmid);
        $arrivalDate = $row[array_search('Arrival Date', EventreeBMIDExport::CSV_HEADERS)];

        $this->assertSame('', $arrivalDate);
        $this->assertNotEquals(date('m/d/Y'), $arrivalDate);
    }

    /*
     * bulkLoadRelationships must compute has_signups / training_signed_up /
     * org_vehicle_insurance for the REQUESTED year, not the current year.
     * Signups, training, and org insurance are created in the current year only;
     * querying a different year must yield all-false flags.
     */

    public function testBulkLoadRelationshipsIsYearCorrect()
    {
        $person = Person::factory()->create();
        $currentYear = current_year();
        $otherYear = $currentYear + 3;

        // On-playa shift signup (has_signups) in the current year.
        $shiftSlot = Slot::factory()->create([
            'begins' => "$currentYear-08-20 00:00:00",
            'ends' => "$currentYear-08-20 06:00:00",
            'position_id' => Position::DIRT,
            'active' => true,
        ]);
        PersonSlot::factory()->create(['person_id' => $person->id, 'slot_id' => $shiftSlot->id]);

        // Training signup (training_signed_up) in the current year.
        $trainingSlot = Slot::factory()->create([
            'begins' => "$currentYear-07-20 09:45:00",
            'ends' => "$currentYear-07-20 17:45:00",
            'position_id' => Position::TRAINING,
            'active' => true,
        ]);
        PersonSlot::factory()->create(['person_id' => $person->id, 'slot_id' => $trainingSlot->id]);

        // Org vehicle insurance flag for the current year.
        PersonEvent::factory()->create([
            'person_id' => $person->id,
            'year' => $currentYear,
            'org_vehicle_insurance' => true,
        ]);

        // Sanity: for the current year, the flags are all true.
        $currentBmid = new Bmid(['person_id' => $person->id, 'year' => $currentYear]);
        Bmid::bulkLoadRelationships(new EloquentCollection([$currentBmid]), [$person->id], $currentYear);
        $this->assertTrue($currentBmid->has_signups);
        $this->assertTrue($currentBmid->training_signed_up);
        $this->assertTrue($currentBmid->org_vehicle_insurance);

        // For a different year, none of these apply.
        $otherBmid = new Bmid(['person_id' => $person->id, 'year' => $otherYear]);
        Bmid::bulkLoadRelationships(new EloquentCollection([$otherBmid]), [$person->id], $otherYear);
        $this->assertFalse($otherBmid->has_signups);
        $this->assertFalse($otherBmid->training_signed_up);
        $this->assertFalse($otherBmid->org_vehicle_insurance);
    }

    /*
     * An orphaned BMID -- a row whose person_id has no matching person -- must not
     * cause a 500 on the index/manage path. bulkLoadRelationships filters null people
     * and re-derives its working id set, so the orphan is simply dropped.
     */

    public function testOrphanedBmidDoesNotCrashIndex()
    {
        // A valid BMID that must still come through.
        $person = Person::factory()->create();
        Bmid::factory()->create(['person_id' => $person->id, 'year' => $this->year]);

        // An orphaned BMID: person_id points at a person that does not exist.
        Bmid::factory()->create(['person_id' => 987654, 'year' => $this->year]);

        $response = $this->json('GET', 'bmid', ['year' => $this->year]);
        $response->assertStatus(200);
        $response->assertJsonFragment(['person_id' => $person->id]);
    }
}
