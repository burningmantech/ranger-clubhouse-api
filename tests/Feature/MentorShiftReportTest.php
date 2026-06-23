<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\PersonMentor;
use App\Models\PersonSlot;
use App\Models\Position;
use App\Models\Role;
use App\Models\Slot;
use App\Models\Timesheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MentorShiftReportTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();

        $this->signInUser();
        $this->addRole(Role::MENTOR);

        Position::factory()->create([
            'id' => Position::ALPHA,
            'title' => 'Alpha',
            'short_title' => 'Alpha',
            'type' => Position::TYPE_TRAINING,
        ]);
    }

    /**
     * Give a person two BONK records in descending year order so the DB is
     * likely to return them non-ascending, forcing the in-memory sort to
     * actually reorder elements.
     */
    private function bonkTwice(Person $person): void
    {
        foreach ([2021, 2019] as $year) {
            PersonMentor::factory()->create([
                'person_id' => $person->id,
                'mentor_id' => $person->id,
                'mentor_year' => $year,
                'status' => PersonMentor::BONK,
            ]);
        }
    }

    /**
     * The slot-based report must serialize an Alpha's prior bonk years as a
     * plain ascending JSON array. Sorting a plucked collection preserves keys,
     * so without ->values() the array serializes as a JSON object.
     */
    public function testAlphaBonksSerializeAsSortedArray(): void
    {
        $slot = Slot::factory()->create([
            'position_id' => Position::ALPHA,
            'begins' => now(),
            'ends' => now()->addHours(4),
            'active' => true,
        ]);

        $alpha = Person::factory()->create(['callsign' => 'Greenhorn']);
        PersonSlot::factory()->create(['person_id' => $alpha->id, 'slot_id' => $slot->id]);
        $this->bonkTwice($alpha);

        $bonks = $this->json('GET', 'mentor/shift-report', ['slot_id' => $slot->id])
            ->assertStatus(200)
            ->json('groups.0.positions.0.slots.0.people.0.bonks');

        $this->assertSame([2019, 2021], $bonks);
    }

    /**
     * The on-duty report must filter on the current year (it previously
     * hardcoded 2025) and serialize bonks as a sorted array.
     */
    public function testOnDutyReportUsesCurrentYearAndSortsBonks(): void
    {
        $alpha = Person::factory()->create(['callsign' => 'Greenhorn']);
        Timesheet::factory()->create([
            'person_id' => $alpha->id,
            'position_id' => Position::ALPHA,
            'on_duty' => now(),
            'off_duty' => null,
        ]);
        $this->bonkTwice($alpha);

        $person = $this->json('GET', 'mentor/shift-report', ['on_duty' => 1])
            ->assertStatus(200)
            ->json('groups.0.positions.0.people.0');

        $this->assertSame($alpha->id, $person['id']);
        $this->assertSame([2019, 2021], $person['bonks']);
    }

    /**
     * The on-duty report means *currently* on duty: a person whose shift has
     * been signed off (off_duty set) must not appear, while a person still
     * signed in (off_duty null) must.
     */
    public function testOnDutyReportExcludesSignedOffShifts(): void
    {
        $onDuty = Person::factory()->create(['callsign' => 'StillHere']);
        Timesheet::factory()->create([
            'person_id' => $onDuty->id,
            'position_id' => Position::ALPHA,
            'on_duty' => now(),
            'off_duty' => null,
        ]);

        $signedOff = Person::factory()->create(['callsign' => 'WentHome']);
        Timesheet::factory()->create([
            'person_id' => $signedOff->id,
            'position_id' => Position::ALPHA,
            'on_duty' => now(),
            'off_duty' => now(),
        ]);

        $people = $this->json('GET', 'mentor/shift-report', ['on_duty' => 1])
            ->assertStatus(200)
            ->json('groups.0.positions.0.people');

        $ids = array_column($people, 'id');
        $this->assertContains($onDuty->id, $ids);
        $this->assertNotContains($signedOff->id, $ids);
    }

    /**
     * When nobody is currently on duty the report must still respond with an
     * object shaped { groups: [], now: ... } so the frontend can read
     * response.groups rather than receiving a bare [] array.
     */
    public function testOnDutyReportReturnsObjectShapeWhenEmpty(): void
    {
        $response = $this->json('GET', 'mentor/shift-report', ['on_duty' => 1])
            ->assertStatus(200);

        $response->assertJsonStructure(['groups', 'now']);
        $this->assertSame([], $response->json('groups'));
        $this->assertNotNull($response->json('now'));
    }
}
