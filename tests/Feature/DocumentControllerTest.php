<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Position;
use App\Models\Role;
use App\Models\Team;
use App\Models\TeamManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentControllerTest extends TestCase
{
    use RefreshDatabase;

    /*
     * have each test have a fresh user that is logged in.
     */

    public function setUp(): void
    {
        parent::setUp();
        $this->signInUser();
    }

    /**
     * Test showing all the documents
     */

    public function testDocumentIndexSuccess()
    {
        $this->addAdminRole();
        $document = Document::factory()->create();
        $response = $this->json('GET', 'document');
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'document.*.id');
    }

    /*
     * Test showing an access document
     */

    public function testDocumentShowSuccess()
    {
        $document = Document::factory()->create();
        $response = $this->json('GET', "document/{$document->id}");
        $response->assertStatus(200);
        $response->assertJson([
            'document' => [
                'id' => $document->id,
                'tag' => $document->tag,
                'description' => $document->description,
                'body' => $document->body,
            ]
        ]);
    }

    /*
     * Test creating a document
     */

    public function testDocumentCreateSuccess()
    {
        $this->addAdminRole();
        $data = [
            'tag' => 'new-tag',
            'description' => 'a description',
            'body' => 'a body'
        ];
        $response = $this->json('POST', 'document', ['document' => $data]);
        $response->assertStatus(200);
        $response->assertJson([
            'document' => array_merge($data, ['person_create_id' => $this->user->id])
        ]);
        $this->assertDatabaseHas('document', $data);
    }

    /*
     * Test updating a document
     */

    public function testDocumentUpdateSuccess()
    {
        $this->addAdminRole();
        $document = Document::factory()->create();
        $data = ['tag' => 'a-new-tag'];

        $response = $this->json('PUT', "document/{$document->id}", ['document' => $data]);
        $response->assertStatus(200);
        $this->assertDatabaseHas('document', [
            'id' => $document->id,
            'tag' => 'a-new-tag'
        ]);
    }

    /**
     * Test no spaces in tag
     */

    public function testNoSpacesInTag()
    {
        $this->addAdminRole();
        $document = Document::factory()->create();
        $data = ['tag' => 'a new tag'];

        $response = $this->json('PUT', "document/{$document->id}", ['document' => $data]);
        $response->assertStatus(422);
        $response->assertJson([
            'errors' => [
                [
                    'source' => ['pointer' => '/data/attributes/tag']
                ]
            ]
        ]);
    }

    /*
     * Test deleting a document
     */

    public function testDocumentDeleteSuccess()
    {
        $this->addAdminRole();
        $document = Document::factory()->create();
        $response = $this->json('DELETE', "document/{$document->id}");
        $response->assertStatus(204);
        $this->assertDatabaseMissing('document', ['id' => $document->id]);
    }

    /*
     * Test resource-edit returns an unsaved document skeleton for a team without a resource document
     */

    public function testResourceEditForTeamWithoutDocument()
    {
        $this->addAdminRole();
        $team = Team::factory()->create(['title' => 'Echelon']);

        $response = $this->json('GET', 'document/resource-edit', [
            'resource_type' => 'team',
            'resource_entity_id' => $team->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'document' => [
                'tag' => 'echelon-team-info',
                'description' => 'Echelon Team Document',
                'resource_type' => 'team',
                'resource_entity_id' => $team->id,
            ]
        ]);
        $this->assertDatabaseMissing('document', ['tag' => 'echelon-team-info']);
    }

    /*
     * Test resource-edit returns the existing document for a team with a resource tag
     */

    public function testResourceEditForTeamWithDocument()
    {
        $this->addAdminRole();
        $document = Document::factory()->create(['tag' => 'echelon-team-info']);
        $team = Team::factory()->create(['title' => 'Echelon', 'resource_tag' => 'echelon-team-info']);

        $response = $this->json('GET', 'document/resource-edit', [
            'resource_type' => 'team',
            'resource_entity_id' => $team->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'document' => [
                'id' => $document->id,
                'tag' => 'echelon-team-info',
                'body' => $document->body,
                'resource_type' => 'team',
                'resource_entity_id' => $team->id,
            ]
        ]);
    }

    /*
     * Test resource-edit returns an unsaved document skeleton for a position without a resource document
     */

    public function testResourceEditForPositionWithoutDocument()
    {
        $this->addAdminRole();
        $team = Team::factory()->create();
        $position = Position::factory()->create(['title' => 'Sandman', 'team_id' => $team->id]);

        $response = $this->json('GET', 'document/resource-edit', [
            'resource_type' => 'position',
            'resource_entity_id' => $position->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'document' => [
                'tag' => 'sandman-resource',
                'description' => 'Sandman Position Document',
                'resource_type' => 'position',
                'resource_entity_id' => $position->id,
            ]
        ]);
        $this->assertDatabaseMissing('document', ['tag' => 'sandman-resource']);
    }

    /*
     * Test resource-edit returns the existing document for a position with a resource tag
     */

    public function testResourceEditForPositionWithDocument()
    {
        $this->addAdminRole();
        $document = Document::factory()->create(['tag' => 'sandman-resource']);
        $team = Team::factory()->create();
        $position = Position::factory()->create([
            'title' => 'Sandman',
            'team_id' => $team->id,
            'resource_tag' => 'sandman-resource'
        ]);

        $response = $this->json('GET', 'document/resource-edit', [
            'resource_type' => 'position',
            'resource_entity_id' => $position->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'document' => [
                'id' => $document->id,
                'tag' => 'sandman-resource',
                'resource_type' => 'position',
                'resource_entity_id' => $position->id,
            ]
        ]);
    }

    /*
     * Test resource-edit allows a team manager holding the team resource management role
     */

    public function testResourceEditAllowedForTeamManagerWithRole()
    {
        $team = Team::factory()->create(['title' => 'Echelon']);
        TeamManager::insert(['team_id' => $team->id, 'person_id' => $this->user->id]);
        $this->addRole(Role::TEAM_RESOURCE_MANAGEMENT);

        $response = $this->json('GET', 'document/resource-edit', [
            'resource_type' => 'team',
            'resource_entity_id' => $team->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['document' => ['tag' => 'echelon-team-info']]);
    }

    /*
     * Test resource-edit is denied when the person is not the team's manager
     */

    public function testResourceEditDeniedForNonTeamManager()
    {
        $team = Team::factory()->create();
        $this->addRole(Role::TEAM_RESOURCE_MANAGEMENT);

        $response = $this->json('GET', 'document/resource-edit', [
            'resource_type' => 'team',
            'resource_entity_id' => $team->id,
        ]);

        $response->assertStatus(403);
        $response->assertJson(['error' => "You are not this team's manager."]);
    }

    /*
     * Test resource-edit is denied when the team manager lacks the team resource management role
     */

    public function testResourceEditDeniedForTeamManagerWithoutRole()
    {
        $team = Team::factory()->create();
        TeamManager::insert(['team_id' => $team->id, 'person_id' => $this->user->id]);

        $response = $this->json('GET', 'document/resource-edit', [
            'resource_type' => 'team',
            'resource_entity_id' => $team->id,
        ]);

        $response->assertStatus(403);
        $response->assertJson(['error' => 'You are not authorized to manage the team resource document.']);
    }

    /*
     * Test resource-edit allows a person holding the position's trainer resource management role
     */

    public function testResourceEditAllowedForTrainerResourceRole()
    {
        $team = Team::factory()->create();
        $position = Position::factory()->create(['title' => 'Sandman', 'team_id' => $team->id]);
        $this->addRole(Role::TRAINER_RESOURCE_MANAGEMENT_BASE | $position->id);

        $response = $this->json('GET', 'document/resource-edit', [
            'resource_type' => 'position',
            'resource_entity_id' => $position->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['document' => ['tag' => 'sandman-resource']]);
    }

    /*
     * Test resource-edit is denied when the person lacks the position's trainer resource management role
     */

    public function testResourceEditDeniedWithoutTrainerResourceRole()
    {
        $team = Team::factory()->create();
        $position = Position::factory()->create(['team_id' => $team->id]);

        $response = $this->json('GET', 'document/resource-edit', [
            'resource_type' => 'position',
            'resource_entity_id' => $position->id,
        ]);

        $response->assertStatus(403);
        $response->assertJson(['error' => "You are not authorized to manage this position's document."]);
    }

    /*
     * Test resource-edit is denied for a non-training position without an associated team
     */

    public function testResourceEditDeniedForPositionWithoutTeam()
    {
        $this->addAdminRole();
        $position = Position::factory()->create(['team_id' => null]);

        $response = $this->json('GET', 'document/resource-edit', [
            'resource_type' => 'position',
            'resource_entity_id' => $position->id,
        ]);

        $response->assertStatus(403);
        $response->assertJson(['error' => 'Position does not have an associated team.']);
    }

    /*
     * Test resource-edit allows the In-Person Training position even without an associated team
     */

    public function testResourceEditAllowedForTrainingPositionWithoutTeam()
    {
        $this->addAdminRole();
        $position = Position::factory()->create([
            'id' => Position::TRAINING,
            'title' => 'Training',
            'team_id' => null
        ]);

        $response = $this->json('GET', 'document/resource-edit', [
            'resource_type' => 'position',
            'resource_entity_id' => $position->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['document' => ['tag' => 'training-resource']]);
    }

    /*
     * Test resource-edit rejects an invalid resource type
     */

    public function testResourceEditRejectsInvalidParameters()
    {
        $this->addAdminRole();

        $response = $this->json('GET', 'document/resource-edit', [
            'resource_type' => 'slot',
            'resource_entity_id' => 1,
        ]);

        $response->assertStatus(422);
    }

    /*
     * Test resource-edit fails for an unknown team
     */

    public function testResourceEditForUnknownTeam()
    {
        $this->addAdminRole();

        $response = $this->json('GET', 'document/resource-edit', [
            'resource_type' => 'team',
            'resource_entity_id' => 999999,
        ]);

        $response->assertStatus(404);
    }

    /*
     * Test resource-delete removes the team's document and clears the resource tag
     */

    public function testResourceDeleteForTeam()
    {
        $this->addAdminRole();
        $document = Document::factory()->create(['tag' => 'echelon-team-info']);
        $team = Team::factory()->create(['title' => 'Echelon', 'resource_tag' => 'echelon-team-info']);

        $response = $this->json('DELETE', 'document/resource-delete', [
            'resource_type' => 'team',
            'resource_entity_id' => $team->id,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('document', ['id' => $document->id]);
        $this->assertDatabaseHas('team', ['id' => $team->id, 'resource_tag' => null]);
    }

    /*
     * Test resource-delete removes the position's document and clears the resource tag
     */

    public function testResourceDeleteForPosition()
    {
        $this->addAdminRole();
        $document = Document::factory()->create(['tag' => 'sandman-resource']);
        $team = Team::factory()->create();
        $position = Position::factory()->create([
            'title' => 'Sandman',
            'team_id' => $team->id,
            'resource_tag' => 'sandman-resource'
        ]);

        $response = $this->json('DELETE', 'document/resource-delete', [
            'resource_type' => 'position',
            'resource_entity_id' => $position->id,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('document', ['id' => $document->id]);
        $this->assertDatabaseHas('position', ['id' => $position->id, 'resource_tag' => '']);
    }

    /*
     * Test resource-delete allows a team manager holding the team resource management role
     */

    public function testResourceDeleteAllowedForTeamManagerWithRole()
    {
        $document = Document::factory()->create(['tag' => 'echelon-team-info']);
        $team = Team::factory()->create(['title' => 'Echelon', 'resource_tag' => 'echelon-team-info']);
        TeamManager::insert(['team_id' => $team->id, 'person_id' => $this->user->id]);
        $this->addRole(Role::TEAM_RESOURCE_MANAGEMENT);

        $response = $this->json('DELETE', 'document/resource-delete', [
            'resource_type' => 'team',
            'resource_entity_id' => $team->id,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('document', ['id' => $document->id]);
    }

    /*
     * Test resource-delete is denied for a person without any management rights
     */

    public function testResourceDeleteDeniedWithoutRoles()
    {
        $document = Document::factory()->create(['tag' => 'echelon-team-info']);
        $team = Team::factory()->create(['resource_tag' => 'echelon-team-info']);

        $response = $this->json('DELETE', 'document/resource-delete', [
            'resource_type' => 'team',
            'resource_entity_id' => $team->id,
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('document', ['id' => $document->id]);
    }

    /*
     * Test resource-delete fails when the team has no resource tag
     */

    public function testResourceDeleteWithoutResourceTag()
    {
        $this->addAdminRole();
        $team = Team::factory()->create();

        $response = $this->json('DELETE', 'document/resource-delete', [
            'resource_type' => 'team',
            'resource_entity_id' => $team->id,
        ]);

        $response->assertStatus(422);
    }

    /*
     * Test resource-delete fails when the tagged document does not exist
     */

    public function testResourceDeleteWithMissingDocument()
    {
        $this->addAdminRole();
        $team = Team::factory()->create(['resource_tag' => 'no-such-document']);

        $response = $this->json('DELETE', 'document/resource-delete', [
            'resource_type' => 'team',
            'resource_entity_id' => $team->id,
        ]);

        $response->assertStatus(404);
    }
}
