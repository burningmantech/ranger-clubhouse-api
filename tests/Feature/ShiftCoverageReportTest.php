<?php

namespace Tests\Feature;

use App\Exceptions\UnacceptableConditionException;
use App\Lib\Reports\ShiftCoverageReport;
use App\Models\Person;
use App\Models\PersonSlot;
use App\Models\Position;
use App\Models\Slot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Characterization tests for ShiftCoverageReport::execute().
 *
 * These lock the externally observable behavior (output shape, period bucketing,
 * head counts, parenthetical labels) so the DRY/KISS refactor of the report can be
 * trusted in the absence of any prior coverage. execute() reads only the slot,
 * person_slot, and person tables, so the fixtures build slots directly and never
 * need Position rows.
 */
class ShiftCoverageReportTest extends TestCase
{
    use RefreshDatabase;

    public int $year = 2025;

    /**
     * Create a slot for a position spanning the given hour-of-day window on the report year.
     */
    private function makeSlot(int $positionId, string $begins, string $ends, int $signedUp = 0): Slot
    {
        return Slot::factory()->create([
            'position_id' => $positionId,
            'begins' => "$this->year-09-01 $begins",
            'ends' => "$this->year-09-01 $ends",
            'signed_up' => $signedUp,
        ]);
    }

    /**
     * Sign a freshly created person up for a slot and return them.
     */
    private function signUp(Slot $slot, string $callsign): Person
    {
        $person = Person::factory()->create(['callsign' => $callsign]);
        PersonSlot::factory()->create([
            'person_id' => $person->id,
            'slot_id' => $slot->id,
        ]);

        return $person;
    }

    /**
     * An unknown coverage type is rejected rather than silently returning empty data.
     */
    public function testThrowsOnUnknownType(): void
    {
        $this->expectException(UnacceptableConditionException::class);
        $this->expectExceptionMessage('Unknown type bogus');

        ShiftCoverageReport::execute($this->year, 'bogus');
    }

    /**
     * The columns mirror the coverage definition order and short titles regardless of data.
     */
    public function testReturnsCoverageColumns(): void
    {
        $report = ShiftCoverageReport::execute($this->year, 'echelon');

        $this->assertSame([
            ['position_id' => Position::ECHELON_FIELD_LEAD, 'short_title' => 'Echelon Lead'],
            ['position_id' => Position::ECHELON_FIELD, 'short_title' => 'Echelon Field'],
            ['position_id' => Position::ECHELON_FIELD_LEAD_TRAINING, 'short_title' => 'Lead Training'],
        ], $report['columns']);
    }

    /**
     * With no base-position shifts, columns are still returned but periods are empty.
     */
    public function testReturnsEmptyPeriodsWhenNoShifts(): void
    {
        $report = ShiftCoverageReport::execute($this->year, 'echelon');

        $this->assertCount(3, $report['columns']);
        $this->assertSame([], $report['periods']);
    }

    /**
     * A ranger signed up for a covered position appears, bucketed under the period
     * defined by the base-position shift, in the matching column.
     */
    public function testBucketsSignedUpRangerIntoPeriod(): void
    {
        // Base ECHELON_FIELD shift defines a single 10:00-18:00 period.
        $this->makeSlot(Position::ECHELON_FIELD, '10:00:00', '18:00:00');

        // A lead signed up for a slot that fully spans the (trimmed) window.
        $leadSlot = $this->makeSlot(Position::ECHELON_FIELD_LEAD, '09:00:00', '19:00:00');
        $this->signUp($leadSlot, 'Zebra');

        $report = ShiftCoverageReport::execute($this->year, 'echelon');

        $this->assertCount(1, $report['periods']);
        $period = $report['periods'][0];

        // Column 0 is "Echelon Lead"; its shifts carry the signed-up person.
        $leadColumn = $period['positions'][0];
        $this->assertSame(Position::ECHELON_FIELD_LEAD, $leadColumn['position_id']);
        $this->assertSame('people', $leadColumn['type']);

        $people = $leadColumn['shifts'][0]['people'];
        $this->assertCount(1, $people);
        $this->assertSame('Zebra', $people[0]['callsign']);

        // The un-staffed columns produce no people.
        $this->assertSame([], $report['periods'][0]['positions'][1]['shifts']);
        $this->assertSame([], $report['periods'][0]['positions'][2]['shifts']);
    }

    /**
     * People within a period are sorted case-insensitively by callsign.
     */
    public function testPeopleAreSortedByCallsign(): void
    {
        $this->makeSlot(Position::ECHELON_FIELD, '10:00:00', '18:00:00');

        $slot = $this->makeSlot(Position::ECHELON_FIELD_LEAD, '09:00:00', '19:00:00');
        $this->signUp($slot, 'zulu');
        $this->signUp($slot, 'Alpha');
        $this->signUp($slot, 'mike');

        $report = ShiftCoverageReport::execute($this->year, 'echelon');

        $people = $report['periods'][0]['positions'][0]['shifts'][0]['people'];
        $callsigns = array_column($people, 'callsign');

        $this->assertSame(['Alpha', 'mike', 'zulu'], $callsigns);
    }

    /**
     * A count-only post returns the summed signed_up head count, not a people list.
     */
    public function testCountOnlyPostReturnsSummedHeadCount(): void
    {
        // Two DIRT_PRE_EVENT slots define the period and carry head counts.
        $this->makeSlot(Position::DIRT_PRE_EVENT, '10:00:00', '18:00:00', signedUp: 4);
        $this->makeSlot(Position::DIRT_PRE_EVENT, '10:00:00', '18:00:00', signedUp: 3);

        $report = ShiftCoverageReport::execute($this->year, 'pre-event');

        // PRE_EVENT column order ends with the people "Dirt" post then the "Dirt Count" post.
        $columns = array_column($report['columns'], 'short_title');
        $countIndex = array_search('Dirt Count', $columns, true);
        $this->assertNotFalse($countIndex);

        $countColumn = $report['periods'][0]['positions'][$countIndex];
        $this->assertSame('count', $countColumn['type']);
        $this->assertSame(7, $countColumn['shifts']);
    }

    /**
     * A signup whose position carries a parenthetical mapping gets that label attached.
     */
    public function testAppliesParentheticalLabel(): void
    {
        // OPERATOR_SMOOTH is one of the COMMAND base positions, so it defines a period.
        $smoothSlot = $this->makeSlot(Position::OPERATOR_SMOOTH, '10:00:00', '18:00:00');
        $this->signUp($smoothSlot, 'Smoothie');

        $report = ShiftCoverageReport::execute($this->year, 'command');

        // Find the "Opr" column, whose parenthetical maps OPERATOR_SMOOTH => 'Smooth'.
        $columns = array_column($report['columns'], 'short_title');
        $oprIndex = array_search('Opr', $columns, true);
        $this->assertNotFalse($oprIndex);

        $person = $report['periods'][0]['positions'][$oprIndex]['shifts'][0]['people'][0];
        $this->assertSame('Smoothie', $person['callsign']);
        $this->assertSame('Smooth', $person['parenthetical']);
    }
}
