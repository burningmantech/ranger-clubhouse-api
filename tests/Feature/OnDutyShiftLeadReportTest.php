<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\PersonPosition;
use App\Models\Position;
use App\Models\Role;
use App\Models\Slot;
use App\Models\Timesheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnDutyShiftLeadReportTest extends TestCase
{
    use RefreshDatabase;

    public int $year;

    public function setUp(): void
    {
        parent::setUp();

        $this->signInUser();
        $this->year = (int)date('Y');

        Position::factory()->create([
            'id' => Position::HQ_WINDOW,
            'title' => 'HQ Window',
            'short_title' => 'HQ',
            'type' => Position::TYPE_FRONTLINE,
        ]);

        Position::factory()->create([
            'id' => Position::OOD,
            'title' => 'Officer of the Day',
            'short_title' => 'OOD',
            'type' => Position::TYPE_COMMAND,
        ]);

        Position::factory()->create([
            'id' => Position::DIRT_GREEN_DOT,
            'title' => 'Dirt - Green Dot',
            'short_title' => 'GD',
            'type' => Position::TYPE_FRONTLINE,
        ]);

        Position::factory()->create([
            'id' => Position::TROUBLESHOOTER,
            'title' => 'Troubleshooter',
            'short_title' => 'TS',
            'type' => Position::TYPE_FRONTLINE,
            'on_sl_report' => true,
        ]);
    }

    /**
     * Put a person on duty (off_duty NULL) on the given position right now.
     */
    private function putOnDuty(Person $person, int $positionId): Timesheet
    {
        return Timesheet::factory()->create([
            'person_id' => $person->id,
            'position_id' => $positionId,
            'on_duty' => now(),
        ]);
    }

    /**
     * The report is restricted to the Event Management role.
     */
    public function testRequiresEventManagementRole(): void
    {
        $this->json('GET', 'timesheet/on-duty-shift-lead-report')->assertStatus(403);
    }

    /**
     * Happy path: on-duty people are bucketed by position class and green dots
     * are counted.
     */
    public function testReportsOnDutyRangersByCategory(): void
    {
        $this->addRole(Role::EVENT_MANAGEMENT);

        $nonDirt = Person::factory()->create(['callsign' => 'Hubcap']);
        $this->putOnDuty($nonDirt, Position::HQ_WINDOW);
        PersonPosition::factory()->create([
            'person_id' => $nonDirt->id,
            'position_id' => Position::TROUBLESHOOTER,
        ]);

        $command = Person::factory()->create(['callsign' => 'Khaki']);
        $this->putOnDuty($command, Position::OOD);

        $greenDot = Person::factory()->create(['callsign' => 'Sparkle']);
        $this->putOnDuty($greenDot, Position::DIRT_GREEN_DOT);

        $response = $this->json('GET', 'timesheet/on-duty-shift-lead-report');
        $response->assertStatus(200);

        $response->assertJson([
            'non_dirt_signups' => [[
                'id' => $nonDirt->id,
                'callsign' => 'Hubcap',
                'is_troubleshooter' => true,
                'positions' => ['TS'],
            ]],
            'command_staff_signups' => [[
                'id' => $command->id,
                'callsign' => 'Khaki',
            ]],
            'dirt_signups' => [[
                'id' => $greenDot->id,
                'callsign' => 'Sparkle',
                'is_greendot_shift' => true,
            ]],
            'green_dot_total' => 1,
            'green_dot_females' => 0,
        ]);
    }

    /**
     * Regression guard: a ranger holding no SL-relevant positions must still
     * return every status key (defaulted to false) and an empty positions list,
     * so consumers get a consistent shape.
     */
    public function testRangerWithoutPositionsHasConsistentShape(): void
    {
        $this->addRole(Role::EVENT_MANAGEMENT);

        $command = Person::factory()->create(['callsign' => 'Khaki']);
        $this->putOnDuty($command, Position::OOD);

        $ranger = $this->json('GET', 'timesheet/on-duty-shift-lead-report')
            ->assertStatus(200)
            ->json('command_staff_signups.0');

        $this->assertFalse($ranger['is_troubleshooter']);
        $this->assertFalse($ranger['is_rsl']);
        $this->assertFalse($ranger['is_ood']);
        $this->assertFalse($ranger['is_greendot']);
        $this->assertFalse($ranger['is_greendot_shift']);
        $this->assertSame([], $ranger['positions']);
    }

    /**
     * Positions staffed below their minimum head count are reported.
     */
    public function testReportsPositionsBelowMinimum(): void
    {
        $this->addRole(Role::EVENT_MANAGEMENT);

        $person = Person::factory()->create(['callsign' => 'Hubcap']);
        $this->putOnDuty($person, Position::HQ_WINDOW);

        Slot::factory()->create([
            'position_id' => Position::HQ_WINDOW,
            'begins' => now()->subHour(),
            'ends' => now()->addHour(),
            'min' => 2,
            'max' => 5,
        ]);

        $response = $this->json('GET', 'timesheet/on-duty-shift-lead-report');
        $response->assertStatus(200);

        $response->assertJson([
            'below_min_positions' => [[
                'position_id' => Position::HQ_WINDOW,
                'min' => 2,
                'max' => 5,
                'on_duty' => 1,
            ]],
        ]);
    }

    /**
     * Completed shifts (off_duty set) are not considered on duty.
     */
    public function testIgnoresSignedOffShifts(): void
    {
        $this->addRole(Role::EVENT_MANAGEMENT);

        $person = Person::factory()->create(['callsign' => 'Khaki']);
        Timesheet::factory()->create([
            'person_id' => $person->id,
            'position_id' => Position::OOD,
            'on_duty' => now()->subHours(3),
            'off_duty' => now()->subHour(),
        ]);

        $response = $this->json('GET', 'timesheet/on-duty-shift-lead-report');
        $response->assertStatus(200);
        $this->assertSame([], $response->json('command_staff_signups'));
    }
}
