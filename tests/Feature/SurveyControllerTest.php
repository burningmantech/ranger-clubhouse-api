<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Models\Person;
use App\Models\PersonSlot;
use App\Models\Position;
use App\Models\Slot;
use App\Models\Survey;
use App\Models\SurveyGroup;
use App\Models\SurveyQuestion;
use App\Models\TraineeStatus;
use App\Models\TrainerStatus;

class SurveyControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    public function setUp(): void
    {
        parent::setUp();
        $this->signInUser();
        $this->addAdminRole();
        $this->surveyPosition = Position::factory()->create([
            'id' => Position::GREEN_DOT_TRAINING,
            'title' => 'Green Dot Training'
        ]);
    }

    /*
     * Get the survey documents
     */

    public function testIndexSurvey()
    {
        $year = 2018;
        Survey::factory()->create(['year' => $year, 'position_id' => $this->surveyPosition->id]);

        $response = $this->json('GET', 'survey', ['year' => $year]);
        $response->assertStatus(200);
        $this->assertCount(1, $response->json()['survey']);
    }

    /*
     * Create a survey document
     */

    public function testCreateSurvey()
    {
        $data = [
            'year' => 2020,
            'type' => Survey::TRAINER,
            'position_id' => $this->surveyPosition->id,
            'title' => 'My Awesome Survey',
            'prologue' => 'Take the survey',
            'epilogue' => 'Did you take it?'
        ];

        $response = $this->json('POST', 'survey', [
            'survey' => $data
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('survey', $data);
    }

    /*
     * Update a survey document
     */

    public function testUpdateSurvey()
    {
        $survey = Survey::factory()->create([ 'position_id' => $this->surveyPosition->id]);

        $response = $this->json('PATCH', "survey/{$survey->id}", [
            'survey' => ['epilogue' => 'epilogue your behind']
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('survey', ['id' => $survey->id, 'epilogue' => 'epilogue your behind']);
    }

    /*
     * Delete a slot
     */

    public function testDeleteSurvey()
    {
        $survey = Survey::factory()->create([ 'position_id' => $this->surveyPosition->id]);
        $surveyId = $survey->id;

        $response = $this->json('DELETE', "survey/{$surveyId}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('survey', ['id' => $surveyId]);
    }

    /*
     * Test duplicate a survey
     */

    public function testDuplicateSurvey()
    {
        $year = 2018;
        $currentYear = current_year();
        $origSurvey = Survey::factory()->create(['year' => $year, 'title' => "$year Survey Title", 'position_id' => $this->surveyPosition->id]);
        $origGroup = SurveyGroup::factory()->create(['survey_id' => $origSurvey->id]);
        $origQuestion = SurveyQuestion::factory()->create(['survey_id' => $origSurvey->id, 'survey_group_id' => $origGroup->id]);

        $response = $this->json('POST', "survey/{$origSurvey->id}/duplicate");
        $response->assertStatus(200);
        $newId = $response->json('survey_id');

        $this->assertDatabaseHas('survey', ['id' => $newId, 'title' => "$currentYear Survey Title"]);
        $this->assertDatabaseHas('survey_group', ['survey_id' => $newId, 'title' => $origGroup->title]);
        $this->assertDatabaseHas('survey_question', ['survey_id' => $newId, 'description' => $origQuestion->description]);
    }


    /*
     * Test prepare a training venue questionnaire
     */


    public function testVenueQuestionnaire()
    {
        $this->buildVenueSurvey();

        $response = $this->json('GET', "survey/questionnaire", [
            'slot_id' => $this->slot->id,
            'type' => Survey::TRAINING
        ]);
        $response->assertStatus(200);

        $survey = $this->survey;
        $venueGroup = $this->venueGroup;
        $venueQ = $this->venueQuestion;
        $trainerGroup = $this->trainerGroup;
        $trainerQ = $this->trainerQuestion;

        $response->assertJson([
            'survey' => [
                'id' => $survey->id,
                'type' => Survey::TRAINING,
                'year' => $survey->year,
                'title' => $survey->title,
            ]
        ]);

        $response->assertJson([
            'survey' => [
                'survey_groups' => [
                    [
                        'id' => $venueGroup->id,
                        'title' => $venueGroup->title,
                        'description' => $venueGroup->description,
                        'survey_questions' => [
                            [
                                'id' => $venueQ->id,
                                'sort_index' => $venueQ->sort_index,
                                'type' => $venueQ->type,
                                'description' => $venueQ->description,
                            ]
                        ]
                    ],

                    [
                        'id' => $trainerGroup->id,
                        'title' => $trainerGroup->title,
                        'description' => $trainerGroup->description,
                        'survey_questions' => [
                            [
                                'id' => $trainerQ->id,
                                'sort_index' => $trainerQ->sort_index,
                                'type' => $trainerQ->type,
                                'description' => $trainerQ->description,
                            ]
                        ]

                    ]
                ]
            ]
        ]);

        $trainer = $this->trainer;

        $response->assertJson([
            'trainers' => [
                [
                    'id' => $trainer->id,
                    'callsign' => $trainer->callsign,
                    'position_id' => Position::TRAINER
                ]
            ]
        ]);

        $slot = $this->slot;
        $response->assertJson([
            'slot' => [
                'id' => $slot->id,
                'begins' => $slot->begins
            ]
        ]);
        //return response()->json(['survey' => $survey, 'trainers' => $trainers, 'slot' => $slot]);

    }

    /*
     * Test submitting a survey response
     */

    private function buildVenueSurvey(): void
    {
        $this->year = current_year();

        $this->trainer = Person::factory()->create();

        $this->survey = Survey::factory()->create(['year' => $this->year, 'position_id' => Position::TRAINING]);
        $surveyId = $this->survey->id;

        $this->venueGroup = SurveyGroup::factory()->create(['survey_id' => $surveyId, 'sort_index' => 1]);
        $this->venueQuestion = SurveyQuestion::factory()->create(['survey_id' => $surveyId, 'survey_group_id' => $this->venueGroup->id]);

        $this->trainerGroup = SurveyGroup::factory()->create(['survey_id' => $surveyId, 'type' => 'trainer', 'sort_index' => 2]);
        $this->trainerQuestion = SurveyQuestion::factory()->create(['survey_id' => $surveyId, 'survey_group_id' => $this->trainerGroup->id]);

        $this->slot = Slot::factory()->create([
            'description' => 'Venue 1',
            'position_id' => Position::TRAINING,
            'begins' => "{$this->year}-01-01 00:00",
            'ends' => "{$this->year}-01-01 00:01"
        ]);
        PersonSlot::factory()->create(['person_id' => $this->user->id, 'slot_id' => $this->slot->id]);
        TraineeStatus::factory()->create(['person_id' => $this->user->id, 'slot_id' => $this->slot->id, 'passed' => true]);
        $this->trainerSlot = Slot::factory()->create([
            'description' => 'Venue 1',
            'position_id' => Position::TRAINER,
            'begins' => "{$this->year}-01-01 00:00",
            'ends' => "{$this->year}-01-01 00:01"
        ]);
        PersonSlot::factory()->create(['person_id' => $this->trainer->id, 'slot_id' => $this->trainerSlot->id]);
        TrainerStatus::factory()->create([
            'person_id' => $this->trainer->id,
            'slot_id' => $this->slot->id,
            'trainer_slot_id' => $this->trainerSlot->id,
            'status' => TrainerStatus::ATTENDED
        ]);
    }

    /*
     * Test a venue questionnaire submission
     */

    public function testVenueSubmitSurvey()
    {
        $this->buildVenueSurvey();

        $venueG = $this->venueGroup;
        $venueQ = $this->venueQuestion;

        $response = $this->json('POST', 'survey/submit', [
            'slot_id' => $this->slot->id,
            'type' => Survey::TRAINING,
            'survey' => [
                [
                    'survey_group_id' => $venueG->id,
                    'answers' => [
                        [
                            'survey_question_id' => $venueQ->id,
                            'response' => 'a response'
                        ]
                    ]
                ],
            ]
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('survey_answer', [
            'person_id' => $this->user->id,
            'slot_id' => $this->slot->id,
            'survey_question_id' => $venueQ->id,
            'response' => 'a response'
        ]);
    }

    /*
    * Test a trainer venue questionnaire submission
    */

    public function testTrainerGroupSubmitSurvey()
    {
        $this->buildVenueSurvey();

        $trainer = $this->trainer;
        $trainerG = $this->trainerGroup;
        $trainerQ = $this->trainerQuestion;

        $response = $this->json('POST', 'survey/submit', [
            'slot_id' => $this->slot->id,
            'type' => Survey::TRAINING,
            'survey' => [
                [
                    'survey_group_id' => $trainerG->id,
                    'trainer_id' => $trainer->id,
                    'answers' => [
                        [
                            'survey_question_id' => $trainerQ->id,
                            'response' => 'a response'
                        ]
                    ]
                ],
            ]
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('survey_answer', [
            'person_id' => $this->user->id,
            'slot_id' => $this->slot->id,
            'survey_question_id' => $trainerQ->id,
            'trainer_id' => $trainer->id,
            'response' => 'a response'
        ]);
    }
}
