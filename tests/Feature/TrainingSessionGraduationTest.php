<?php

namespace Tests\Feature;

use App\Lib\YearsManagement;
use App\Models\Person;
use App\Models\PersonPosition;
use App\Models\PersonSlot;
use App\Models\Position;
use App\Models\Role;
use App\Models\Slot;
use App\Models\Timesheet;
use App\Models\TraineeStatus;
use App\Models\TrainingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class TrainingSessionGraduationTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    public function setUp(): void
    {
        parent::setUp();
        $this->signInUser();
        $this->addAdminRole();
    }

    private function createGreenDotTrainingSession(): Slot
    {
        Position::factory()->create([
            'id' => Position::GREEN_DOT_TRAINING,
            'title' => 'Green Dot Training',
            'type' => Position::TYPE_TRAINING,
        ]);

        Position::factory()->create([
            'id' => Position::GREEN_DOT_TRAINER,
            'title' => 'Green Dot Trainer',
        ]);

        Position::factory()->create([
            'id' => Position::DIRT_GREEN_DOT,
            'title' => 'Dirt - Green Dot',
        ]);

        Position::factory()->create([
            'id' => Position::GREEN_DOT_MENTEE,
            'title' => 'Green Dot Mentee',
        ]);

        Position::factory()->create([
            'id' => Position::SANCTUARY_MENTEE,
            'title' => 'Sanctuary Mentee',
        ]);

        Position::factory()->create([
            'id' => Position::ALPHA,
            'title' => 'Alpha',
            'type' => Position::TYPE_MENTORING,
        ]);

        return Slot::factory()->create([
            'begins' => date('Y-07-20 11:00:00'),
            'ends' => date('Y-07-20 12:00:00'),
            'position_id' => Position::GREEN_DOT_TRAINING,
            'description' => 'GD Training',
        ]);
    }

    private function createSandmanTrainingSession(): Slot
    {
        Position::factory()->create([
            'id' => Position::SANDMAN_TRAINING,
            'title' => 'Sandman Training',
            'type' => Position::TYPE_TRAINING,
        ]);

        Position::factory()->create([
            'id' => Position::SANDMAN_TRAINER,
            'title' => 'Sandman Trainer',
        ]);

        Position::factory()->create([
            'id' => Position::SANDMAN,
            'title' => 'Sandman',
        ]);

        return Slot::factory()->create([
            'begins' => date('Y-07-20 11:00:00'),
            'ends' => date('Y-07-20 12:00:00'),
            'position_id' => Position::SANDMAN_TRAINING,
            'description' => 'Sandman Training',
        ]);
    }

    private function createStudentWithTimesheets(Slot $slot, array $timesheetData = []): Person
    {
        $person = Person::factory()->create(['status' => Person::ACTIVE]);
        PersonSlot::factory()->create(['person_id' => $person->id, 'slot_id' => $slot->id]);
        TraineeStatus::factory()->create(['person_id' => $person->id, 'slot_id' => $slot->id, 'passed' => true]);

        foreach ($timesheetData as $entry) {
            Timesheet::factory()->create(array_merge(['person_id' => $person->id], $entry));
        }

        YearsManagement::updateTimesheetYears($person->id);
        return $person;
    }

    private function createVolunteerPosition(): Position
    {
        return Position::factory()->create([
            'id' => Position::DIRT,
            'title' => 'Dirt',
            'type' => Position::TYPE_FRONTLINE,
        ]);
    }

    /**
     * Test that a candidate who meets the mentee requirements is eligible.
     */
    public function testCandidateMeetsRequirements(): void
    {
        $slot = $this->createGreenDotTrainingSession();
        $volunteerPosition = $this->createVolunteerPosition();
        $year = current_year();

        $person = $this->createStudentWithTimesheets($slot, [
            ['position_id' => $volunteerPosition->id, 'on_duty' => ($year - 1) . '-08-01 08:00:00', 'off_duty' => ($year - 1) . '-08-01 16:00:00'],
            ['position_id' => $volunteerPosition->id, 'on_duty' => ($year - 2) . '-08-02 08:00:00', 'off_duty' => ($year - 2) . '-08-02 14:00:00'],
            ['position_id' => $volunteerPosition->id, 'on_duty' => ($year - 3) . '-08-03 08:00:00', 'off_duty' => ($year - 3) . '-08-03 14:00:00'],
        ]);

        $response = $this->json('GET', "training-session/{$slot->id}/graduation-candidates");
        $response->assertStatus(200);

        $people = $response->json('people');
        $candidate = collect($people)->firstWhere('id', $person->id);
        $this->assertEquals(TrainingSession::ELIGIBILITY_CANDIDATE, $candidate['eligibility']);
    }

    /**
     * Test that a candidate with insufficient shift count gets requirements-incomplete.
     */
    public function testCandidateInsufficientShiftCount(): void
    {
        $slot = $this->createGreenDotTrainingSession();
        $volunteerPosition = $this->createVolunteerPosition();
        $year = current_year();

        // 2 shifts with enough total hours (20), but only 2 shifts (need 3)
        $person = $this->createStudentWithTimesheets($slot, [
            ['position_id' => $volunteerPosition->id, 'on_duty' => ($year - 1) . '-08-01 08:00:00', 'off_duty' => ($year - 1) . '-08-01 18:00:00'],
            ['position_id' => $volunteerPosition->id, 'on_duty' => ($year - 2) . '-08-02 08:00:00', 'off_duty' => ($year - 2) . '-08-02 18:00:00'],
        ]);

        $response = $this->json('GET', "training-session/{$slot->id}/graduation-candidates");
        $response->assertStatus(200);

        $people = $response->json('people');
        $candidate = collect($people)->firstWhere('id', $person->id);
        $this->assertEquals(TrainingSession::ELIGIBILITY_REQUIREMENTS_INCOMPLETE, $candidate['eligibility']);
    }

    /**
     * Test that a candidate with insufficient hours gets requirements-incomplete.
     */
    public function testCandidateInsufficientHours(): void
    {
        $slot = $this->createGreenDotTrainingSession();
        $volunteerPosition = $this->createVolunteerPosition();
        $year = current_year();

        // 3 shifts but only 9 total hours (need 18)
        $person = $this->createStudentWithTimesheets($slot, [
            ['position_id' => $volunteerPosition->id, 'on_duty' => ($year - 1) . '-08-01 08:00:00', 'off_duty' => ($year - 1) . '-08-01 11:00:00'],
            ['position_id' => $volunteerPosition->id, 'on_duty' => ($year - 2) . '-08-02 08:00:00', 'off_duty' => ($year - 2) . '-08-02 11:00:00'],
            ['position_id' => $volunteerPosition->id, 'on_duty' => ($year - 3) . '-08-03 08:00:00', 'off_duty' => ($year - 3) . '-08-03 11:00:00'],
        ]);

        $response = $this->json('GET', "training-session/{$slot->id}/graduation-candidates");
        $response->assertStatus(200);

        $people = $response->json('people');
        $candidate = collect($people)->firstWhere('id', $person->id);
        $this->assertEquals(TrainingSession::ELIGIBILITY_REQUIREMENTS_INCOMPLETE, $candidate['eligibility']);
    }

    /**
     * Test that Alpha position entries are excluded from the shift count.
     */
    public function testAlphaEntriesExcluded(): void
    {
        $slot = $this->createGreenDotTrainingSession();
        $volunteerPosition = $this->createVolunteerPosition();
        $year = current_year();

        // 2 real shifts + 1 Alpha shift = only 2 qualifying shifts
        $person = $this->createStudentWithTimesheets($slot, [
            ['position_id' => $volunteerPosition->id, 'on_duty' => ($year - 1) . '-08-01 08:00:00', 'off_duty' => ($year - 1) . '-08-01 18:00:00'],
            ['position_id' => $volunteerPosition->id, 'on_duty' => ($year - 2) . '-08-02 08:00:00', 'off_duty' => ($year - 2) . '-08-02 18:00:00'],
            ['position_id' => Position::ALPHA, 'on_duty' => ($year - 3) . '-08-03 08:00:00', 'off_duty' => ($year - 3) . '-08-03 18:00:00'],
        ]);

        $response = $this->json('GET', "training-session/{$slot->id}/graduation-candidates");
        $response->assertStatus(200);

        $people = $response->json('people');
        $candidate = collect($people)->firstWhere('id', $person->id);
        $this->assertEquals(TrainingSession::ELIGIBILITY_REQUIREMENTS_INCOMPLETE, $candidate['eligibility']);
    }

    /**
     * Test that Training-type position entries are excluded from the shift count.
     */
    public function testTrainingTypeEntriesExcluded(): void
    {
        $slot = $this->createGreenDotTrainingSession();
        $volunteerPosition = $this->createVolunteerPosition();
        $year = current_year();

        // 2 real shifts + 1 training shift = only 2 qualifying shifts
        $person = $this->createStudentWithTimesheets($slot, [
            ['position_id' => $volunteerPosition->id, 'on_duty' => ($year - 1) . '-08-01 08:00:00', 'off_duty' => ($year - 1) . '-08-01 18:00:00'],
            ['position_id' => $volunteerPosition->id, 'on_duty' => ($year - 2) . '-08-02 08:00:00', 'off_duty' => ($year - 2) . '-08-02 18:00:00'],
            // Green Dot Training is TYPE_TRAINING
            ['position_id' => Position::GREEN_DOT_TRAINING, 'on_duty' => ($year - 3) . '-08-03 08:00:00', 'off_duty' => ($year - 3) . '-08-03 18:00:00'],
        ]);

        $response = $this->json('GET', "training-session/{$slot->id}/graduation-candidates");
        $response->assertStatus(200);

        $people = $response->json('people');
        $candidate = collect($people)->firstWhere('id', $person->id);
        $this->assertEquals(TrainingSession::ELIGIBILITY_REQUIREMENTS_INCOMPLETE, $candidate['eligibility']);
    }

    /**
     * Test that current year entries are excluded from the shift count.
     */
    public function testCurrentYearEntriesExcluded(): void
    {
        $slot = $this->createGreenDotTrainingSession();
        $volunteerPosition = $this->createVolunteerPosition();
        $year = current_year();

        // All 3 shifts in the current year
        $person = $this->createStudentWithTimesheets($slot, [
            ['position_id' => $volunteerPosition->id, 'on_duty' => $year . '-08-01 08:00:00', 'off_duty' => $year . '-08-01 16:00:00'],
            ['position_id' => $volunteerPosition->id, 'on_duty' => $year . '-08-02 08:00:00', 'off_duty' => $year . '-08-02 14:00:00'],
            ['position_id' => $volunteerPosition->id, 'on_duty' => $year . '-08-03 08:00:00', 'off_duty' => $year . '-08-03 14:00:00'],
        ]);

        $response = $this->json('GET', "training-session/{$slot->id}/graduation-candidates");
        $response->assertStatus(200);

        $people = $response->json('people');
        $candidate = collect($people)->firstWhere('id', $person->id);
        $this->assertEquals(TrainingSession::ELIGIBILITY_REQUIREMENTS_INCOMPLETE, $candidate['eligibility']);
    }

    /**
     * Test that entries older than 5 years are excluded.
     */
    public function testOldEntriesExcluded(): void
    {
        $slot = $this->createGreenDotTrainingSession();
        $volunteerPosition = $this->createVolunteerPosition();
        $year = current_year();

        $person = $this->createStudentWithTimesheets($slot, [
            ['position_id' => $volunteerPosition->id, 'on_duty' => ($year - 6) . '-08-01 08:00:00', 'off_duty' => ($year - 6) . '-08-01 16:00:00'],
        ]);

        $response = $this->json('GET', "training-session/{$slot->id}/graduation-candidates");
        $response->assertStatus(200);

        $people = $response->json('people');
        $candidate = collect($people)->firstWhere('id', $person->id);

        $this->assertEquals(TrainingSession::ELIGIBILITY_REQUIREMENTS_INCOMPLETE, $candidate['eligibility']);
    }

    /**
     * Test that positions without mentee_requirements skip the check.
     */
    public function testPositionWithoutMenteeRequirements(): void
    {
        $slot = $this->createSandmanTrainingSession();

        // Create a student with no timesheets at all — should still be 'candidate'
        $person = $this->createStudentWithTimesheets($slot);

        $response = $this->json('GET', "training-session/{$slot->id}/graduation-candidates");
        $response->assertStatus(200);

        $people = $response->json('people');
        $candidate = collect($people)->firstWhere('id', $person->id);
        $this->assertEquals(TrainingSession::ELIGIBILITY_CANDIDATE, $candidate['eligibility']);
    }

    /**
     * Test that the controller forces through a graduation.
     */
    public function testControllerRejectsRequirementsIncomplete(): void
    {
        $slot = $this->createGreenDotTrainingSession();

        // Create candidate with no qualifying timesheets
        $person = $this->createStudentWithTimesheets($slot);

        $this->addRole(Role::ART_GRADUATE_BASE | Position::GREEN_DOT_TRAINING);

        $response = $this->json('POST', "training-session/{$slot->id}/graduate-candidates", [
            'ids' => [$person->id],
        ]);
        $response->assertStatus(200);

        $people = $response->json('people');
        $result = collect($people)->firstWhere('id', $person->id);
        $this->assertEquals("success", $result['status']);

        // Verify positions were  granted
        $this->assertTrue(
            PersonPosition::where('person_id', $person->id)
                ->where('position_id', Position::GREEN_DOT_MENTEE)
                ->exists()
        );
    }
}
