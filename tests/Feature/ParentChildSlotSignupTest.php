<?php

namespace Tests\Feature;

use App\Exceptions\ScheduleSignUpException;
use App\Models\PersonOnlineCourse;
use App\Models\PersonPhoto;
use App\Models\PersonPosition;
use App\Models\Person;
use App\Models\Position;
use App\Models\Schedule;
use App\Models\Slot;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Parent/Child sign-up slot behaviour.
 *
 * Domain model:
 *   - A "child" slot carries parent_signup_slot_id pointing at a "parent" slot
 *     that begins at the same time (e.g. Mentee -> Ridealong).
 *   - Intended rules (per Schedule::computeSignups doc-block):
 *       1. A child's own sign-ups may not exceed the child's max.
 *       2. The combined (parent + child) sign-ups may not exceed the PARENT's max
 *          (the parent max is the shared capacity pool).
 *
 * Each test asserts the *correct* behaviour. A failing test therefore marks a
 * place where the feature misbehaves.
 *
 * Naming below:
 *   Pm = parent max, Cm = child max, Ps = parent signed_up, Cs = child signed_up.
 */
class ParentChildSlotSignupTest extends TestCase
{
    use RefreshDatabase;

    public string $year;

    public function setUp(): void
    {
        parent::setUp();

        $this->signInUser();
        Mail::fake();

        $this->year = date('Y');
        Carbon::setTestNow("{$this->year}-02-01 12:00:00");

        // Required for the online-course requirement check used by the HTTP path.
        Position::factory()->create([
            'id' => Position::TRAINING,
            'title' => 'Training',
            'type' => 'Training',
        ]);
        $this->setting('OnlineCourseDisabledAllowSignups', false);
    }

    /**
     * Build a linked parent/child slot pair with the given maxes and pre-existing
     * signed_up counts. Returns freshly loaded [$parent, $child].
     */
    private function makeLinked(int $pm, int $cm, int $ps = 0, int $cs = 0): array
    {
        $begins = "{$this->year}-08-30 09:00:00";
        $ends = "{$this->year}-08-30 17:00:00";

        $parentPos = Position::factory()->create(['title' => 'Ridealong', 'type' => 'Frontline']);
        $childPos = Position::factory()->create([
            'title' => 'Mentee',
            'type' => 'Frontline',
            'parent_position_id' => $parentPos->id,
        ]);

        $parent = Slot::factory()->create([
            'position_id' => $parentPos->id,
            'begins' => $begins,
            'ends' => $ends,
            'description' => 'Ridealong Aug 30',
            'max' => $pm,
            'min' => 0,
            'signed_up' => $ps,
            'active' => true,
        ]);

        $child = Slot::factory()->create([
            'position_id' => $childPos->id,
            'begins' => $begins,
            'ends' => $ends,
            'description' => 'Mentee Aug 30',
            'max' => $cm,
            'min' => 0,
            'signed_up' => $cs,
            'active' => true,
            'parent_signup_slot_id' => $parent->id,
        ]);

        return [Slot::find($parent->id), Slot::find($child->id)];
    }

    /* ===================================================================
     * GROUP A — Child slot sign-ups (computeSignups, OP_ADD)
     *
     * The returned signed_up is the value the UI renders as "N / max" for the
     * child shift. It must equal the real number of people in the child slot
     * after the add, never the child's max.
     * =================================================================== */

    /** A1: Empty pool, Pm=4 Cm=2. One child sign-up -> reports 1, not full. */
    public function testChildSignupEmptyPoolReportsOne()
    {
        [, $child] = $this->makeLinked(pm: 4, cm: 2, ps: 0, cs: 0);

        [$signUps, $isFull] = Schedule::computeSignups($child, Schedule::OP_ADD);

        $this->assertEquals(1, $signUps, 'one child sign-up should report 1');
        $this->assertFalse($isFull, 'pool of 4 is not full after 1 sign-up');
    }

    /**
     * A2: REPORTED BUG. Pm=1 Cm=2, empty. One child sign-up.
     * The pool (parent max 1) becomes full, but only ONE real person exists in
     * the child slot, so signed_up must be 1 — not the child max of 2.
     */
    public function testChildSignupDoesNotOverstateCountWhenPoolBinds()
    {
        [, $child] = $this->makeLinked(pm: 1, cm: 2, ps: 0, cs: 0);

        [$signUps, $isFull] = Schedule::computeSignups($child, Schedule::OP_ADD);

        $this->assertEquals(1, $signUps,
            'one real sign-up must report 1, even though the shared pool is now full');
    }

    /**
     * A3: Bigger overstatement. Pm=2 Cm=3, one parent already (Ps=1).
     * Adding the first child fills the pool (1+1=2=Pm) but there is only ONE
     * person in the child slot -> signed_up must be 1, not the child max of 3.
     */
    public function testChildSignupReportsRealCountNotChildMax()
    {
        [, $child] = $this->makeLinked(pm: 2, cm: 3, ps: 1, cs: 0);

        [$signUps] = Schedule::computeSignups($child, Schedule::OP_ADD);

        $this->assertEquals(1, $signUps,
            'child slot has 1 real person; must not report the child max (3)');
    }

    /** A4: Child own-max reached reports the real count. Pm=10 Cm=2, Cs=1. */
    public function testChildSignupAtOwnMaxReportsRealCount()
    {
        [, $child] = $this->makeLinked(pm: 10, cm: 2, ps: 0, cs: 1);

        [$signUps, $isFull] = Schedule::computeSignups($child, Schedule::OP_ADD);

        $this->assertEquals(2, $signUps, 'second of two child sign-ups reports 2');
        $this->assertTrue($isFull, 'child own max (2) reached');
    }

    /** A5: Child blocked once its own max is reached. Pm=10 Cm=1, Cs=1. */
    public function testChildBlockedAtOwnMax()
    {
        [, $child] = $this->makeLinked(pm: 10, cm: 1, ps: 0, cs: 1);

        try {
            Schedule::computeSignups($child, Schedule::OP_ADD);
            $this->fail('expected ScheduleSignUpException');
        } catch (ScheduleSignUpException $e) {
            // The child's own capacity is the one that is full.
            $this->assertEquals('Mentee', $e->fullPositionTitle);
        }
    }

    /** A6: Child blocked when shared pool is full even though child max not reached. */
    public function testChildBlockedWhenPoolFull()
    {
        // Pm=2 Cm=5, two parents already -> pool full.
        [, $child] = $this->makeLinked(pm: 2, cm: 5, ps: 2, cs: 0);

        try {
            Schedule::computeSignups($child, Schedule::OP_ADD);
            $this->fail('expected ScheduleSignUpException');
        } catch (ScheduleSignUpException $e) {
            // The shared parent pool is the one that is full, not the child.
            $this->assertEquals('Ridealong', $e->fullPositionTitle);
        }
    }

    /* ===================================================================
     * GROUP B — Parent slot sign-ups (computeSignups, OP_ADD)
     * =================================================================== */

    /** B1: Parent sign-up combined count. Pm=4 Cm=2, Cs=1. */
    public function testParentSignupReportsCombined()
    {
        [$parent] = $this->makeLinked(pm: 4, cm: 2, ps: 0, cs: 1);

        [$signUps, $isFull, , $linked, $combinedMax] = Schedule::computeSignups($parent, Schedule::OP_ADD);

        $this->assertEquals(2, $signUps, 'combined pool usage is 1 child + 1 new parent = 2');
        $this->assertFalse($isFull, 'pool of 4 not full');
        $this->assertEquals(4, $combinedMax);

        // The linked child entry must reflect the child's REAL count (1), not its max.
        $this->assertEquals(1, $linked[0]['signed_up'],
            'linked child count must be the real 1, not the child max');
    }

    /** B2: Parent blocked when pool full. Pm=2 Cm=2, Cs=2. */
    public function testParentBlockedWhenPoolFull()
    {
        [$parent] = $this->makeLinked(pm: 2, cm: 2, ps: 0, cs: 2);

        $this->expectException(ScheduleSignUpException::class);
        Schedule::computeSignups($parent, Schedule::OP_ADD);
    }

    /* ===================================================================
     * GROUP C — becameFull flag must only reflect an actual ADD.
     *
     * computeSignups guards the becameFull computation with `self::OP_ADD`
     * (a constant string) instead of `$op == self::OP_ADD`, so it can fire on
     * a pure QUERY. becameFull must be false for a non-add.
     * =================================================================== */

    /** C1: Child QUERY must never report becameFull. Pm=2, Ps=1 -> combined+1==Pm. */
    public function testChildQueryDoesNotReportBecameFull()
    {
        [, $child] = $this->makeLinked(pm: 2, cm: 5, ps: 1, cs: 0);

        [, , $becameFull] = Schedule::computeSignups($child, Schedule::OP_QUERY);

        $this->assertFalse($becameFull, 'a QUERY must not flag became_full');
    }

    /** C2: Parent QUERY must never report becameFull. Pm=2, Cs=1 -> combined+1==Pm. */
    public function testParentQueryDoesNotReportBecameFull()
    {
        [$parent] = $this->makeLinked(pm: 2, cm: 5, ps: 0, cs: 1);

        [, , $becameFull] = Schedule::computeSignups($parent, Schedule::OP_QUERY);

        $this->assertFalse($becameFull, 'a QUERY must not flag became_full');
    }

    /* ===================================================================
     * GROUP D — End-to-end HTTP reproduction of the reported scenario.
     * Ridealong (parent, max 1) / Mentee (child, max 2). One person signs up
     * for the Mentee shift via the API.
     * =================================================================== */

    public function testHttpOneMenteeSignupDoesNotShowSlotFull()
    {
        [$parent, $child] = $this->makeLinked(pm: 1, cm: 2, ps: 0, cs: 0);

        // The acting user must hold the child position and meet requirements.
        $this->addPosition($child->position_id);
        $this->markOnlineCoursePassed($this->user);
        $this->setupPhotoApproved($this->user);

        $response = $this->json('POST', "person/{$this->user->id}/schedule", [
            'slot_id' => $child->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => Schedule::SUCCESS]);

        // Exactly one real row in person_slot for the child slot.
        $this->assertDatabaseHas('person_slot', [
            'person_id' => $this->user->id,
            'slot_id' => $child->id,
        ]);
        $realCount = \App\Models\PersonSlot::where('slot_id', $child->id)->count();
        $this->assertEquals(1, $realCount, 'exactly one real sign-up exists');

        // The API must not claim the Mentee shift holds 2 people after one sign-up.
        $reported = $response->json('signed_up');
        $this->assertEquals(1, $reported,
            "Mentee shift reported signed_up={$reported} after a single sign-up");
    }

    /* ===================================================================
     * GROUP E — Schedule listing (the calendar grid in the screenshot).
     * Schedule::findForQuery renders slot_signed_up for each shift. With
     * Ridealong(parent,max 1)/Mentee(child,max 2) and ONE mentee signed up,
     * the Mentee shift must display 1, not 2 (which renders as a full 2/2).
     * =================================================================== */

    public function testScheduleListingDoesNotShowChildAsFull()
    {
        [$parent, $child] = $this->makeLinked(pm: 1, cm: 2, ps: 0, cs: 1);

        // The user must hold both positions for the shifts to appear in the listing.
        $this->addPosition($parent->position_id);
        $this->addPosition($child->position_id);

        [$entries] = Schedule::findForQuery($this->user->id, (int)$this->year);

        $childEntry = collect($entries)->firstWhere('id', $child->id);
        $this->assertNotNull($childEntry, 'Mentee shift should be in the listing');

        $this->assertEquals(1, $childEntry->slot_signed_up,
            "Mentee shift listing shows slot_signed_up={$childEntry->slot_signed_up} (max {$childEntry->slot_max}) for ONE sign-up");
    }

    private function markOnlineCoursePassed(Person $person): void
    {
        $poc = new PersonOnlineCourse;
        $poc->person_id = $person->id;
        $poc->completed_at = now();
        $poc->position_id = Position::TRAINING;
        $poc->type = PersonOnlineCourse::TYPE_MOODLE;
        $poc->year = now()->year;
        $poc->saveWithoutValidation();
    }

    private function setupPhotoApproved(Person $person): void
    {
        $photo = PersonPhoto::factory()->create([
            'person_id' => $person->id,
            'status' => PersonPhoto::APPROVED,
        ]);
        $person->person_photo_id = $photo->id;
        $person->saveWithoutValidation();
    }
}
