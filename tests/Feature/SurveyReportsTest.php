<?php

namespace Tests\Feature;

use App\Exceptions\UnacceptableConditionException;
use App\Lib\SurveyReports;
use App\Models\Person;
use App\Models\Position;
use App\Models\Slot;
use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyGroup;
use App\Models\SurveyQuestion;
use App\Models\Timesheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class SurveyReportsTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    public function setUp(): void
    {
        parent::setUp();
        $this->signInUser();
    }

    /**
     * Build a TRAINER survey with a trainer group, a slot, and a trainer.
     *
     * @return array{survey: Survey, slot: Slot, group: SurveyGroup, trainer: Person}
     */
    private function buildTrainerSurvey(): array
    {
        $position = Position::factory()->create();
        $year = (int) date('Y');

        $survey = Survey::factory()->create([
            'type' => Survey::TRAINER,
            'position_id' => $position->id,
            'year' => $year,
        ]);

        $slot = Slot::factory()->create([
            'position_id' => $position->id,
            'begins' => "$year-06-01 09:00:00",
            'ends' => "$year-06-01 17:00:00",
        ]);

        $group = SurveyGroup::factory()->create([
            'survey_id' => $survey->id,
            'type' => SurveyGroup::TYPE_TRAINER,
        ]);

        $trainer = Person::factory()->create();

        return compact('survey', 'slot', 'group', 'trainer');
    }

    /**
     * A survey answer whose can_share_name is false must never leak the respondent's
     * identity, even when the report is built with includePerson enabled.
     */
    public function testOmitsPersonWhenCanShareNameIsFalse(): void
    {
        ['survey' => $survey, 'slot' => $slot, 'group' => $group, 'trainer' => $trainer] = $this->buildTrainerSurvey();

        $question = SurveyQuestion::factory()->create([
            'survey_id' => $survey->id,
            'survey_group_id' => $group->id,
            'type' => SurveyQuestion::TYPE_TEXT,
        ]);

        $sharing = Person::factory()->create();
        $anonymous = Person::factory()->create();

        SurveyAnswer::create([
            'survey_id' => $survey->id,
            'survey_group_id' => $group->id,
            'survey_question_id' => $question->id,
            'slot_id' => $slot->id,
            'trainer_id' => $trainer->id,
            'person_id' => $sharing->id,
            'response' => 'Great trainer',
            'can_share_name' => true,
        ]);

        SurveyAnswer::create([
            'survey_id' => $survey->id,
            'survey_group_id' => $group->id,
            'survey_question_id' => $question->id,
            'slot_id' => $slot->id,
            'trainer_id' => $trainer->id,
            'person_id' => $anonymous->id,
            'response' => 'Prefer to stay anonymous',
            'can_share_name' => false,
        ]);

        $reports = SurveyReports::buildSurveyReports($survey, $trainer->id, includePerson: true);

        $responses = $this->collectTrainerResponses($reports, $trainer->id, $question->id);
        $this->assertCount(2, $responses);

        $shared = collect($responses)->firstWhere('answer', 'Great trainer');
        $hidden = collect($responses)->firstWhere('answer', 'Prefer to stay anonymous');

        $this->assertArrayHasKey('person', $shared);
        $this->assertEquals($sharing->id, $shared['person']['id']);

        $this->assertArrayNotHasKey('person', $hidden);
    }

    /**
     * The trainer report must always expose a summarized_ratings key, even when
     * the survey has no summarize_rating questions.
     */
    public function testTrainerReportAlwaysHasSummarizedRatingsKey(): void
    {
        ['survey' => $survey, 'slot' => $slot, 'group' => $group, 'trainer' => $trainer] = $this->buildTrainerSurvey();

        $question = SurveyQuestion::factory()->create([
            'survey_id' => $survey->id,
            'survey_group_id' => $group->id,
            'type' => SurveyQuestion::TYPE_TEXT,
            'summarize_rating' => false,
        ]);

        SurveyAnswer::create([
            'survey_id' => $survey->id,
            'survey_group_id' => $group->id,
            'survey_question_id' => $question->id,
            'slot_id' => $slot->id,
            'trainer_id' => $trainer->id,
            'person_id' => Person::factory()->create()->id,
            'response' => 'Feedback text',
            'can_share_name' => true,
        ]);

        $reports = SurveyReports::buildSurveyReports($survey, $trainer->id, includePerson: true);

        $trainerReport = $this->findTrainerReport($reports);
        $this->assertNotNull($trainerReport);
        $this->assertObjectHasProperty('summarized_ratings', $trainerReport);
        $this->assertSame([], $trainerReport->summarized_ratings);
    }

    /**
     * A person may only retrieve a mentoring questionnaire for a slot they actually worked.
     */
    public function testRetrieveMentoringSurveyRejectsNonWorker(): void
    {
        [$survey, $slot] = $this->buildMentoringSurvey();

        $stranger = Person::factory()->create();

        $this->expectException(UnacceptableConditionException::class);
        SurveyReports::retrieveMentoringSurvey(Survey::MENTOR_FOR_MENTEES, $slot->id, $stranger);
    }

    /**
     * A person who worked the survey's position at the slot may retrieve the mentoring targets.
     */
    public function testRetrieveMentoringSurveyAllowsWorker(): void
    {
        [$survey, $slot] = $this->buildMentoringSurvey();

        $worker = Person::factory()->create();
        Timesheet::factory()->create([
            'person_id' => $worker->id,
            'position_id' => $survey->position_id,
            'slot_id' => $slot->id,
            'on_duty' => $slot->begins,
            'off_duty' => $slot->ends,
        ]);

        $target = Person::factory()->create();
        Timesheet::factory()->create([
            'person_id' => $target->id,
            'position_id' => $survey->mentoring_position_id,
            'on_duty' => $slot->begins,
            'off_duty' => $slot->ends,
        ]);

        [$resolvedSlot, $resolvedSurvey, $targets] = SurveyReports::retrieveMentoringSurvey(
            Survey::MENTOR_FOR_MENTEES,
            $slot->id,
            $worker
        );

        $this->assertEquals($slot->id, $resolvedSlot->id);
        $this->assertEquals($survey->id, $resolvedSurvey->id);
        $this->assertCount(1, $targets);
        $this->assertEquals($target->id, $targets[0]->id);
    }

    /**
     * Build a MENTOR_FOR_MENTEES survey with its shift position and mentoring position.
     *
     * @return array{0: Survey, 1: Slot}
     */
    private function buildMentoringSurvey(): array
    {
        $shiftPosition = Position::factory()->create();
        $mentoringPosition = Position::factory()->create();
        $year = (int) date('Y');

        $survey = Survey::factory()->create([
            'type' => Survey::MENTOR_FOR_MENTEES,
            'position_id' => $shiftPosition->id,
            'mentoring_position_id' => $mentoringPosition->id,
            'year' => $year,
        ]);

        $slot = Slot::factory()->create([
            'position_id' => $shiftPosition->id,
            'begins' => "$year-06-01 09:00:00",
            'ends' => "$year-06-01 17:00:00",
        ]);

        return [$survey, $slot];
    }

    /**
     * Locate the trainer group report within a set of survey reports.
     */
    private function findTrainerReport(array $reports): ?object
    {
        foreach ($reports as $report) {
            if ($report->type == SurveyGroup::TYPE_TRAINER) {
                return $report;
            }
        }

        return null;
    }

    /**
     * Flatten the response entries for a given trainer and question.
     *
     * @return array<int,array>
     */
    private function collectTrainerResponses(array $reports, int $trainerId, int $questionId): array
    {
        $trainerReport = $this->findTrainerReport($reports);
        if (!$trainerReport) {
            return [];
        }

        foreach ($trainerReport->trainers as $trainer) {
            if ($trainer['trainer_id'] != $trainerId) {
                continue;
            }
            foreach ($trainer['questions'] ?? [] as $question) {
                if ($question['id'] != $questionId) {
                    continue;
                }
                $responses = [];
                foreach ($question['slots'] as $slot) {
                    foreach ($slot['responses'] as $response) {
                        $responses[] = $response;
                    }
                }
                return $responses;
            }
        }

        return [];
    }
}
