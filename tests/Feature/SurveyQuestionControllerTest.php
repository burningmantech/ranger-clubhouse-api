<?php

namespace Tests\Feature;

use App\Models\Position;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Models\Survey;
use App\Models\SurveyGroup;
use App\Models\SurveyQuestion;

class SurveyQuestionControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    public Survey $survey;
    public SurveyGroup $surveyGroup;

    public function setUp(): void
    {
        parent::setUp();
        $this->signInUser();
        $this->addAdminRole();
        $this->surveyPosition = Position::factory()->create([
            'id' => Position::GREEN_DOT_TRAINING,
            'title' => 'Green Dot Training'
        ]);
        $this->survey = Survey::factory()->create(['position_id' => $this->surveyPosition->id]);
        $this->surveyGroup = SurveyGroup::factory()->create(['survey_id' => $this->survey->id]);
    }

    /*
     * Get the survey group documents
     */

    public function testIndexSurveyQuestion()
    {
        $survey = $this->survey;
        $surveyGroup = $this->surveyGroup;
        $surveyQuestion = SurveyQuestion::factory()->create([
            'survey_id' => $survey->id,
            'survey_group_id' => $surveyGroup->id,
        ]);

        $response = $this->json('GET', 'survey-question', ['survey_id' => $survey->id]);
        $response->assertStatus(200);
        $this->assertCount(1, $response->json()['survey_question']);
        $response->assertJson([
            'survey_question' => [
                [
                    'id' => $surveyQuestion->id
                ]
            ]
        ]);
    }

    /*
     * Create a surveyQuestion document
     */

    public function testCreateSurveyQuestion()
    {
        $survey = $this->survey;
        $surveyGroup = $this->surveyGroup;

        $data = [
            'survey_id' => $survey->id,
            'survey_group_id' => $surveyGroup->id,
            'sort_index' => 99,
            'description' => 'This is a question',
            'is_required' => true,
            'type' => 'options',
            'options' => '1. Option',
        ];

        $response = $this->json('POST', 'survey-question', [
            'survey_question' => $data
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('survey_question', $data);
    }

    /*
     * Update a surveyQuestion document
     */

    public function testUpdateSurveyQuestion()
    {
        $survey = $this->survey;
        $surveyGroup = $this->surveyGroup;
        $surveyQuestion = SurveyQuestion::factory()->create([
            'survey_id' => $survey->id,
            'survey_group_id' => $surveyGroup->id,

        ]);

        $response = $this->json('PATCH', "survey-question/{$surveyQuestion->id}", [
            'survey_question' => ['description' => 'You like cake?']
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('survey_question', ['id' => $surveyQuestion->id, 'description' => 'You like cake?']);
    }

    /*
     * Delete a slot
     */

    public function testDeleteSurveyQuestion()
    {
        $survey = $this->survey;
        $surveyGroup = $this->surveyGroup;
        $surveyQuestion = SurveyQuestion::factory()->create([
            'survey_id' => $survey->id,
            'survey_group_id' => $surveyGroup->id,

        ]);
        $surveyQuestionId = $surveyQuestion->id;

        $response = $this->json('DELETE', "survey-question/{$surveyQuestionId}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('survey_question', ['id' => $surveyQuestionId]);
    }
}
