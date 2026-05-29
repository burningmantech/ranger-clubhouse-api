<?php

namespace Tests\Feature;

use App\Lib\BMIDManagement;
use App\Models\AccessDocument;
use App\Models\Bmid;
use App\Models\Person;
use App\Models\PersonPosition;
use App\Models\PersonSlot;
use App\Models\Position;
use App\Models\Provision;
use App\Models\Slot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class BMIDManagementTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    private int $year;

    public function setUp(): void
    {
        parent::setUp();

        // Creating access documents writes an audit row keyed to the actor.
        $this->signInUser();

        $this->year = current_year();

        // Both are read by the code under test; the box office date is required.
        $this->setting('TAS_BoxOfficeOpenDate', date('Y-09-01'));
        $this->setting('TAS_DefaultWAPDate', date('Y-08-15'));
    }

    /**
     * Create a position row (BADGE_TITLES / sanity-check titles look these up).
     */
    private function createPosition(int $id, string $title): Position
    {
        return Position::factory()->create(['id' => $id, 'title' => $title]);
    }

    /**
     * Create an active slot beginning on the given date and sign the person up.
     */
    private function signUpForShift(Person $person, int $positionId, string $begins): Slot
    {
        $slot = Slot::factory()->create([
            'position_id' => $positionId,
            'begins' => $begins,
            'ends' => date('Y-m-d H:i:s', strtotime("$begins +1 hour")),
            'active' => true,
            'description' => 'Shift',
        ]);

        PersonSlot::factory()->create(['person_id' => $person->id, 'slot_id' => $slot->id]);

        return $slot;
    }

    /**
     * Pull the person ids returned for a given sanity-check bucket.
     *
     * @param array<int, array<string, mixed>> $result
     * @return array<int, int>
     */
    private function bucketPersonIds(array $result, string $type): array
    {
        foreach ($result as $bucket) {
            if ($bucket['type'] === $type) {
                return collect($bucket['people'])->pluck('id')->all();
            }
        }

        $this->fail("Bucket {$type} not found in sanity check result.");
    }

    /*
     * setBMIDTitles() assigns the right titles to each eligible person, returns
     * the correct per-person id (regression: it used to leak the last loop id to
     * every badge), and skips people with no claimed ticket/WAP or training.
     */

    public function testSetBMIDTitlesAssignsTitlesToEligiblePeople()
    {
        // Shift Lead, eligible via a claimed WAP.
        $shiftLead = Person::factory()->create(['callsign' => 'Aaa']);
        PersonPosition::factory()->create(['person_id' => $shiftLead->id, 'position_id' => Position::RSC_SHIFT_LEAD]);
        AccessDocument::factory()->create([
            'person_id' => $shiftLead->id,
            'type' => AccessDocument::WAP,
            'status' => AccessDocument::CLAIMED,
        ]);

        // 007, eligible via an In-Person Training sign-up.
        $double07 = Person::factory()->create(['callsign' => 'Bbb']);
        PersonPosition::factory()->create(['person_id' => $double07->id, 'position_id' => Position::DOUBLE_OH_7]);
        $this->signUpForShift($double07, Position::TRAINING, date('Y-08-01 09:00:00'));

        // OOD, but ineligible: no ticket/WAP, no training sign-up.
        $ineligible = Person::factory()->create(['callsign' => 'Ccc']);
        PersonPosition::factory()->create(['person_id' => $ineligible->id, 'position_id' => Position::OOD]);

        $badges = BMIDManagement::setBMIDTitles();

        $this->assertCount(2, $badges);

        $byId = collect($badges)->keyBy('id');

        $this->assertTrue($byId->has($shiftLead->id), 'Shift Lead badge should carry its own person id');
        $this->assertEquals('Shift Lead', $byId[$shiftLead->id]['title1']);
        $this->assertNull($byId[$shiftLead->id]['title3']);
        $this->assertEquals($shiftLead->callsign, $byId[$shiftLead->id]['callsign']);

        $this->assertTrue($byId->has($double07->id), '007 badge should carry its own person id');
        $this->assertEquals('007', $byId[$double07->id]['title3']);
        $this->assertNull($byId[$double07->id]['title1']);

        $this->assertFalse($byId->has($ineligible->id), 'Person with no ticket/training must be skipped');

        $this->assertDatabaseHas('bmid', [
            'person_id' => $shiftLead->id,
            'year' => $this->year,
            'title1' => 'Shift Lead',
        ]);
        $this->assertDatabaseHas('bmid', [
            'person_id' => $double07->id,
            'year' => $this->year,
            'title3' => '007',
        ]);
    }

    /*
     * The 'alpha' category returns BMIDs for Alpha & Prospective people only.
     */

    public function testRetrieveCategoryAlpha()
    {
        $alpha = Person::factory()->create(['status' => Person::ALPHA]);
        $prospective = Person::factory()->create(['status' => Person::PROSPECTIVE]);
        $active = Person::factory()->create(['status' => Person::ACTIVE]);

        $bmids = BMIDManagement::retrieveCategoryToManage($this->year, 'alpha');
        $ids = collect($bmids)->pluck('person_id')->all();

        $this->assertContains($alpha->id, $ids);
        $this->assertContains($prospective->id, $ids);
        $this->assertNotContains($active->id, $ids);
    }

    /*
     * A BMID-status category returns only BMIDs in that status.
     */

    public function testRetrieveCategoryByBmidStatus()
    {
        $submitted = Person::factory()->create();
        Bmid::factory()->create(['person_id' => $submitted->id, 'year' => $this->year, 'status' => Bmid::SUBMITTED]);

        $inPrep = Person::factory()->create();
        Bmid::factory()->create(['person_id' => $inPrep->id, 'year' => $this->year, 'status' => Bmid::IN_PREP]);

        $bmids = BMIDManagement::retrieveCategoryToManage($this->year, Bmid::SUBMITTED);
        $ids = collect($bmids)->pluck('person_id')->all();

        $this->assertContains($submitted->id, $ids);
        $this->assertNotContains($inPrep->id, $ids);
    }

    /*
     * The 'no-shifts' category returns BMIDs for people with no shift this year.
     */

    public function testRetrieveCategoryNoShifts()
    {
        $withShift = Person::factory()->create();
        Bmid::factory()->create(['person_id' => $withShift->id, 'year' => $this->year]);
        $this->signUpForShift($withShift, 101, date('Y-08-20 09:00:00'));

        $withoutShift = Person::factory()->create();
        Bmid::factory()->create(['person_id' => $withoutShift->id, 'year' => $this->year]);

        $bmids = BMIDManagement::retrieveCategoryToManage($this->year, 'no-shifts');
        $ids = collect($bmids)->pluck('person_id')->all();

        $this->assertContains($withoutShift->id, $ids);
        $this->assertNotContains($withShift->id, $ids);
    }

    /*
     * The default ("special") category picks up showers, qualifying provisions,
     * and any-time access documents, and ignores plain BMIDs.
     */

    public function testRetrieveCategorySpecial()
    {
        $showers = Person::factory()->create();
        Bmid::factory()->create(['person_id' => $showers->id, 'year' => $this->year, 'showers' => true]);

        $provision = Person::factory()->create();
        Bmid::factory()->create(['person_id' => $provision->id, 'year' => $this->year]);
        Provision::factory()->create([
            'person_id' => $provision->id,
            'type' => Provision::WET_SPOT,
            'status' => Provision::AVAILABLE,
            'source_year' => $this->year,
        ]);

        $anyTime = Person::factory()->create();
        AccessDocument::factory()->create([
            'person_id' => $anyTime->id,
            'type' => AccessDocument::WAP,
            'status' => AccessDocument::CLAIMED,
            'access_any_time' => true,
        ]);

        $plain = Person::factory()->create();
        Bmid::factory()->create(['person_id' => $plain->id, 'year' => $this->year]);

        $bmids = BMIDManagement::retrieveCategoryToManage($this->year, 'default');
        $ids = collect($bmids)->pluck('person_id')->all();

        $this->assertContains($showers->id, $ids);
        $this->assertContains($provision->id, $ids);
        $this->assertContains($anyTime->id, $ids);
        $this->assertNotContains($plain->id, $ids);
    }

    /*
     * sanityCheckForYear() always returns the four buckets in order.
     */

    public function testSanityCheckReturnsAllBuckets()
    {
        $result = BMIDManagement::sanityCheckForYear($this->year);

        $this->assertEquals([
            'shifts-before-access-date',
            'shifts-before-submitted-wap',
            'shifts-no-wap',
            'spt-before-box-office',
        ], array_column($result, 'type'));
    }

    /*
     * A person with an early shift but no WAP/SC lands in 'shifts-no-wap'.
     */

    public function testSanityCheckFlagsEarlyShiftWithoutWap()
    {
        $this->createPosition(101, 'Greeter');
        $person = Person::factory()->create(['status' => Person::ACTIVE]);
        // After Aug 10th but before the Aug 15th event-start cutoff.
        $this->signUpForShift($person, 101, date('Y-08-12 09:00:00'));

        $result = BMIDManagement::sanityCheckForYear($this->year);

        $this->assertContains($person->id, $this->bucketPersonIds($result, 'shifts-no-wap'));
        $this->assertNotContains($person->id, $this->bucketPersonIds($result, 'shifts-before-access-date'));
    }

    /*
     * A claimed WAP with an access date later than a signed-up shift lands the
     * person in 'shifts-before-access-date'.
     */

    public function testSanityCheckFlagsShiftBeforeAccessDate()
    {
        $this->createPosition(101, 'Greeter');
        $person = Person::factory()->create(['status' => Person::ACTIVE]);
        $this->signUpForShift($person, 101, date('Y-08-16 09:00:00'));

        AccessDocument::factory()->create([
            'person_id' => $person->id,
            'type' => AccessDocument::WAP,
            'status' => AccessDocument::CLAIMED,
            'access_date' => date('Y-08-20 00:00:00'),
        ]);

        $result = BMIDManagement::sanityCheckForYear($this->year);

        $this->assertContains($person->id, $this->bucketPersonIds($result, 'shifts-before-access-date'));
        $this->assertNotContains($person->id, $this->bucketPersonIds($result, 'shifts-before-submitted-wap'));
        // Having a live WAP keeps them out of the no-WAP bucket.
        $this->assertNotContains($person->id, $this->bucketPersonIds($result, 'shifts-no-wap'));
    }

    /*
     * A *submitted* WAP routes the same situation to the submitted bucket.
     */

    public function testSanityCheckFlagsShiftBeforeSubmittedWap()
    {
        $this->createPosition(101, 'Greeter');
        $person = Person::factory()->create(['status' => Person::ACTIVE]);
        $this->signUpForShift($person, 101, date('Y-08-16 09:00:00'));

        AccessDocument::factory()->create([
            'person_id' => $person->id,
            'type' => AccessDocument::WAP,
            'status' => AccessDocument::SUBMITTED,
            'access_date' => date('Y-08-20 00:00:00'),
        ]);

        $result = BMIDManagement::sanityCheckForYear($this->year);

        $this->assertContains($person->id, $this->bucketPersonIds($result, 'shifts-before-submitted-wap'));
        $this->assertNotContains($person->id, $this->bucketPersonIds($result, 'shifts-before-access-date'));
    }

    /*
     * Any-time access clears a person even when they have an early shift.
     */

    public function testSanityCheckAnyTimeAccessClearsPerson()
    {
        $this->createPosition(101, 'Greeter');
        $person = Person::factory()->create(['status' => Person::ACTIVE]);
        $this->signUpForShift($person, 101, date('Y-08-16 09:00:00'));

        AccessDocument::factory()->create([
            'person_id' => $person->id,
            'type' => AccessDocument::WAP,
            'status' => AccessDocument::QUALIFIED,
            'access_any_time' => true,
        ]);

        $result = BMIDManagement::sanityCheckForYear($this->year);

        $this->assertNotContains($person->id, $this->bucketPersonIds($result, 'shifts-before-access-date'));
        $this->assertNotContains($person->id, $this->bucketPersonIds($result, 'shifts-before-submitted-wap'));
        $this->assertNotContains($person->id, $this->bucketPersonIds($result, 'shifts-no-wap'));
    }

    /*
     * A Special Price Ticket holder whose WAP opens before the box office is
     * flagged in 'spt-before-box-office'.
     */

    public function testSanityCheckFlagsSptBeforeBoxOffice()
    {
        $person = Person::factory()->create(['status' => Person::ACTIVE]);

        AccessDocument::factory()->create([
            'person_id' => $person->id,
            'type' => AccessDocument::SPT,
            'status' => AccessDocument::QUALIFIED,
        ]);
        AccessDocument::factory()->create([
            'person_id' => $person->id,
            'type' => AccessDocument::WAP,
            'status' => AccessDocument::QUALIFIED,
            'access_date' => date('Y-08-20 00:00:00'), // before the Sep 1 box office date
        ]);

        $result = BMIDManagement::sanityCheckForYear($this->year);

        $this->assertContains($person->id, $this->bucketPersonIds($result, 'spt-before-box-office'));
    }
}
