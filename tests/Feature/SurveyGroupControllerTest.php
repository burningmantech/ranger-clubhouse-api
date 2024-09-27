<?php

namespace Tests\Feature;

use App\Models\Position;
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
        $this->surveyPosition = Position::factory()->create([
            'id' => Position::GREEN_DOT_TRAINING,
            'title' => 'Green Dot Training'
        ]);
        $this->survey = Survey::factory()->create(['position_id' => $this->surveyPosition->id]);
    }

    /*
     * Get the survey group documents
     */

    public function testIndexSurveyGroup()
    {
        $surveyGroup = SurveyGroup::factory()->create(['survey_id' => $this->survey->id]);

        $response = $this->json('GET', 'survey-group', [ 'survey_id' => $this->survey->id]);
        $response->assertStatus(200);
        $this->assertCount(1, $response->json()['survey_group']);
        $response->assertJson([
            'survey_group' => [[
                'id' => $surveyGroup->id,
            ]]
        ]);
    }

    /*
     * Create a surveyGroup document
     */

    public function testCreateSurveyGroup()
    {
        $data = [
            'survey_id' => $this->survey->id,
            'sort_index' => 99,
            'title' => 'A Survey Group',
            'description' => 'This is a group',
        ];

        $response = $this->json('POST', 'survey-group', ['survey_group' => $data]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('survey_group', $data);
    }

    /*
     * Update a surveyGroup document
     */

    public function testUpdateSurveyGroup()
    {
        $surveyGroup = SurveyGroup::factory()->create(['survey_id' => $this->survey->id]);

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
        $surveyGroup = SurveyGroup::factory()->create(['survey_id' => $this->survey->id]);
        $surveyGroupId = $surveyGroup->id;

        $response = $this->json('DELETE', "survey-group/{$surveyGroupId}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('survey_group', [ 'id' => $surveyGroupId ]);
    }
}
