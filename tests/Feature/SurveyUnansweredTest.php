<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\Position;
use App\Models\Slot;
use App\Models\Survey;
use App\Models\Timesheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class SurveyUnansweredTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    /**
     * Drive Survey::retrieveUnansweredForPersonYear into the mentoring branch and
     * confirm the returned session lists the mentoring target's callsign. This proves
     * the $targets->person relation resolves after the eager load was added.
     */
    public function testMentoringSessionListsTargetCallsign(): void
    {
        $this->signInUser();

        $year = current_year();

        $mentorPosition = Position::factory()->create(['title' => 'Mentor Shift']);
        $menteePosition = Position::factory()->create(['title' => 'Mentee Shift']);

        Survey::factory()->create([
            'type' => Survey::MENTEES_FOR_MENTOR,
            'year' => $year,
            'active' => true,
            'position_id' => $mentorPosition->id,
            'mentoring_position_id' => $menteePosition->id,
        ]);

        $slot = Slot::factory()->create([
            'position_id' => $mentorPosition->id,
            'begins' => now(),
            'ends' => now()->addHours(4),
            'active' => true,
        ]);

        Timesheet::factory()->create([
            'person_id' => $this->user->id,
            'position_id' => $mentorPosition->id,
            'slot_id' => $slot->id,
            'on_duty' => now(),
            'off_duty' => now()->addHours(4),
        ]);

        $target = Person::factory()->create(['callsign' => 'Greenhorn']);
        Timesheet::factory()->create([
            'person_id' => $target->id,
            'position_id' => $menteePosition->id,
            'on_duty' => now()->addMinutes(30),
            'off_duty' => now()->addHours(3),
        ]);

        $result = Survey::retrieveUnansweredForPersonYear($this->user->id, $year, Person::ACTIVE);

        $this->assertCount(1, $result['sessions']);
        $session = $result['sessions'][0];
        $this->assertSame(Survey::MENTEES_FOR_MENTOR, $session['type']);
        $this->assertSame($slot->id, $session['id']);
        $this->assertCount(1, $session['mentoring']);
        $this->assertSame($target->id, $session['mentoring'][0]['id']);
        $this->assertSame('Greenhorn', $session['mentoring'][0]['callsign']);
    }
}
