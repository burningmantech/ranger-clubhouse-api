<?php

namespace Tests\Feature;

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

    public function setUp(): void
    {
        parent::setUp();
        $this->signInUser();
        $this->addAdminRole();
    }

    /*
     * Get the survey group documents
     */

    public function testIndexSurveyQuestion()
    {
        $survey = Survey::factory()->create();
        $surveyGroup = SurveyGroup::factory()->create(['survey_id' => $survey->id]);
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
        $survey = Survey::factory()->create();
        $surveyGroup = SurveyGroup::factory()->create(['survey_id' => $survey->id]);

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
        $survey = Survey::factory()->create();
        $surveyGroup = SurveyGroup::factory()->create(['survey_id' => $survey->id]);
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
        $survey = Survey::factory()->create();
        $surveyGroup = SurveyGroup::factory()->create(['survey_id' => $survey->id]);
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
