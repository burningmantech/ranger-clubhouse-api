<?php

namespace Tests\Feature;

use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
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

    public function testDocumentIndexSuccess() {
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
                'id'          => $document->id,
                'tag'        => $document->tag,
                'description'      => $document->description,
                'body' => $document->body,
            ]
        ]);
    }

    /*
     * Test creating a document
     */

    public function testDocumentCreateSuccess() {
        $this->addAdminRole();
        $data = [
            'tag' => 'new-tag',
            'description' => 'a description',
            'body' => 'a body'
        ];
        $response = $this->json('POST', 'document', ['document' =>  $data]);
        $response->assertStatus(200);
        $response->assertJson([
            'document' => array_merge($data, [ 'person_create_id' => $this->user->id])
        ]);
        $this->assertDatabaseHas('document', $data);
    }

    /*
     * Test updating a document
     */

    public function testDocumentUpdateSuccess() {
        $this->addAdminRole();
        $document = Document::factory()->create();
        $data = [ 'tag' => 'a new tag' ];

        $response = $this->json('PUT', "document/{$document->id}", ['document' =>  $data]);
        $response->assertStatus(200);
        $this->assertDatabaseHas('document', [
            'id' => $document->id,
            'tag' => 'a new tag'
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
        $this->assertDatabaseMissing('document', [ 'id' => $document->id ]);
    }
}
