<?php

namespace Tests\Feature;

use App\Lib\Reports\PayrollReport;
use App\Models\Person;
use App\Models\Position;
use App\Models\Timesheet;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

/**
 * Regression tests for PayrollReport::execute().
 *
 * These lock in a handful of fixes made to the overlap query, the meal-break
 * split, and the truncation/notes handling, plus the pre-existing employee_id
 * bucketing rules that must not be disturbed by those fixes.
 */
class PayrollReportTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    public string $year = '2025';

    /**
     * Create a position eligible for the payroll report.
     */
    private function makePosition(array $attributes = []): Position
    {
        return Position::factory()->create($attributes);
    }

    /**
     * Create a person with an explicit employee_id (defaults to a non-blank, non-zero id).
     */
    private function makePerson(array $attributes = []): Person
    {
        return Person::factory()->create(array_merge([
            'employee_id' => 'EMP-' . $this->faker->unique()->numerify('####'),
        ], $attributes));
    }

    /**
     * Create a timesheet entry, setting every fillable field explicitly since
     * TimesheetFactory::definition() returns an empty array.
     */
    private function makeTimesheet(Person $person, Position $position, string $onDuty, ?string $offDuty, array $attributes = []): Timesheet
    {
        return Timesheet::factory()->create(array_merge([
            'person_id' => $person->id,
            'position_id' => $position->id,
            'on_duty' => $onDuty,
            'off_duty' => $offDuty,
            'review_status' => Timesheet::STATUS_UNVERIFIED,
        ], $attributes));
    }

    /**
     * A shift that began before the report window's start time and is still on duty
     * (off_duty null) must still surface in 'people', flagged as still_on_duty.
     *
     * Regression: the overlap query previously excluded rows like this.
     */
    public function testShiftStartingBeforeWindowStillOnDutyAppearsInPeople(): void
    {
        $position = $this->makePosition();
        $person = $this->makePerson(['callsign' => 'StillOut']);

        $this->makeTimesheet($person, $position, "$this->year-09-01 04:00:00", null);

        $report = PayrollReport::execute(
            "$this->year-09-01 08:00:00",
            "$this->year-09-01 20:00:00",
            0,
            0,
            [$position->id]
        );

        $this->assertCount(1, $report['people']);
        $shift = $report['people'][0]['shifts'][0];
        $this->assertTrue($shift['still_on_duty'] ?? false);
    }

    /**
     * A 6-hour shift with break_after=5 (hours) and break_duration=90 (minutes) does not
     * fit both work segments plus the meal break (5h + 90m = 6.5h > 6h shift), so no
     * meal_adjusted split should be produced. If one ever is, its second half must not
     * have a negative duration (off_duty strictly after on_duty).
     *
     * Regression: before the guard was added, computeMealBreak() would still be invoked
     * for a duration too short to hold the break, yielding a second_half.off_duty earlier
     * than its second_half.on_duty.
     */
    public function testMealBreakEligibleShiftNeverProducesNegativeSecondHalf(): void
    {
        $position = $this->makePosition();
        $person = $this->makePerson();

        $this->makeTimesheet($person, $position, "$this->year-09-01 06:00:00", "$this->year-09-01 12:00:00");

        $report = PayrollReport::execute(
            "$this->year-09-01 00:00:00",
            "$this->year-09-01 23:59:00",
            5,
            90,
            [$position->id]
        );

        $shift = $report['people'][0]['shifts'][0];

        if (array_key_exists('meal_adjusted', $shift)) {
            $secondHalf = $shift['meal_adjusted']['second_half'];
            $this->assertTrue(
                Carbon::parse($secondHalf['off_duty'])->gt(Carbon::parse($secondHalf['on_duty'])),
                'meal_adjusted second_half off_duty must be strictly after second_half on_duty'
            );
        } else {
            // The 6-hour shift can't fit a 5h + 90m split, so no split - and a note should say so.
            $this->assertStringContainsString('meal break', $shift['notes']);
        }
    }

    /**
     * A shift whose duration, after truncation to the report window, is under a
     * minute must still be present in the shifts array with an explanatory note,
     * not silently dropped.
     */
    public function testSubMinuteTruncatedShiftIsKeptWithNote(): void
    {
        $position = $this->makePosition();
        $person = $this->makePerson();

        // Truncated on_duty (06:00:00) to off_duty (06:00:20) is only 20 seconds.
        $this->makeTimesheet($person, $position, "$this->year-09-01 05:59:50", "$this->year-09-01 06:00:20");

        $report = PayrollReport::execute(
            "$this->year-09-01 06:00:00",
            "$this->year-09-01 18:00:00",
            0,
            0,
            [$position->id]
        );

        $this->assertCount(1, $report['people']);
        $shifts = $report['people'][0]['shifts'];
        $this->assertCount(1, $shifts);

        $shift = $shifts[0];
        $this->assertLessThan(60, $shift['duration']);
        $this->assertStringContainsString('under a minute', $shift['notes']);
    }

    /**
     * A person with employee_id '0' (allowed to work, but uncompensated) is excluded
     * entirely from both 'people' and 'people_without_ids'.
     */
    public function testEmployeeIdZeroIsExcludedEntirely(): void
    {
        $position = $this->makePosition();
        $person = $this->makePerson(['employee_id' => '0']);

        $this->makeTimesheet($person, $position, "$this->year-09-01 09:00:00", "$this->year-09-01 12:00:00");

        $report = PayrollReport::execute(
            "$this->year-09-01 08:00:00",
            "$this->year-09-01 20:00:00",
            0,
            0,
            [$position->id]
        );

        $this->assertSame([], $report['people']);
        $this->assertSame([], $report['people_without_ids']);
    }

    /**
     * A person with a blank/null employee_id appears in 'people_without_ids', not 'people'.
     */
    public function testBlankEmployeeIdAppearsInPeopleWithoutIds(): void
    {
        $position = $this->makePosition();
        $person = $this->makePerson(['employee_id' => null]);

        $this->makeTimesheet($person, $position, "$this->year-09-01 09:00:00", "$this->year-09-01 12:00:00");

        $report = PayrollReport::execute(
            "$this->year-09-01 08:00:00",
            "$this->year-09-01 20:00:00",
            0,
            0,
            [$position->id]
        );

        $this->assertSame([], $report['people']);
        $this->assertCount(1, $report['people_without_ids']);
        $this->assertSame($person->id, $report['people_without_ids'][0]['id']);
    }
}
