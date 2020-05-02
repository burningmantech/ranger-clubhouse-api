<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Models\Survey;
use App\Models\SurveyGroup;

class SurveyGroupControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    public function setUp() : void
    {
        parent::setUp();
        $this->signInUser();
        $this->addAdminRole();
    }

    /*
     * Get the survey group documents
     */

    public function testIndexSurveyGroup()
    {
        factory(SurveyGroup::class)->create();

        $response = $this->json('GET', 'survey-group', [ 'survey_id' => 1]);
        $response->assertStatus(200);
        $this->assertCount(1, $response->json()['survey_group']);
    }

    /*
     * Create a surveyGroup document
     */

    public function testCreateSurveyGroup()
    {
        $survey = factory(Survey::class)->create();

        $data = [
            'survey_id' => $survey->id,
            'sort_index' => 99,
            'title' => 'A Survey Group',
            'description' => 'This is a group',
            'is_trainer_group' => true,
        ];

        $response = $this->json('POST', 'survey-group', [
            'survey_group' => $data
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('survey_group', $data);
    }

    /*
     * Update a surveyGroup document
     */

    public function testUpdateSurveyGroup()
    {
        $surveyGroup = factory(SurveyGroup::class)->create();

        $response = $this->json('PATCH', "survey-group/{$surveyGroup->id}", [
            'survey_group' => [ 'title' => 'Fork Your Survey' ]
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('survey_group', [ 'id' => $surveyGroup->id, 'title' => 'Fork Your Survey' ]);
    }

    /*
     * Delete a slot
     */

    public function testDeleteSurveyGroup()
    {
        $surveyGroup = factory(SurveyGroup::class)->create();
        $surveyGroupId = $surveyGroup->id;

        $response = $this->json('DELETE', "survey-group/{$surveyGroupId}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('survey_group', [ 'id' => $surveyGroupId ]);
    }
}
