<?php

namespace Tests\Feature;

use App\Models\Position;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for TimesheetController::payrollReport(), hit through the real
 * GET /timesheet/payroll HTTP endpoint.
 */
class TimesheetControllerPayrollReportTest extends TestCase
{
    use RefreshDatabase;

    private Position $position;

    public function setUp(): void
    {
        parent::setUp();

        $this->signInWithRole(Role::PAYROLL);
        $this->position = Position::factory()->create();
    }

    private function validParams(array $overrides = []): array
    {
        return array_merge([
            'start_time' => '2025-09-01 08:00:00',
            'end_time' => '2025-09-01 20:00:00',
            'break_duration' => 30,
            'break_after' => 5,
            'position_ids' => [$this->position->id],
        ], $overrides);
    }

    /**
     * A negative break_duration is rejected as a validation error rather than
     * silently accepted and fed into the meal-break math.
     */
    public function testNegativeBreakDurationIsRejected(): void
    {
        $response = $this->json('GET', 'timesheet/payroll', $this->validParams(['break_duration' => -1]));

        $response->assertStatus(422);
    }

    /**
     * Valid, non-negative parameters succeed and return the report payload shape.
     */
    public function testValidParametersReturnSuccess(): void
    {
        $response = $this->json('GET', 'timesheet/payroll', $this->validParams());

        $response->assertStatus(200);
        $response->assertJsonStructure(['people', 'people_without_ids']);
    }
}
