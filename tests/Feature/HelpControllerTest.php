<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Role;

use App\Models\Help;

class HelpControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    public function setUp() : void
    {
        parent::setUp();
        $this->signInUser();
    }

    /*
     * Get the help documents
     */

    public function testIndexHelp()
    {
        Help::factory()->create();

        $response = $this->json('GET', 'help');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json()['help']);
    }

    /*
     * Create a help document
     */

    public function testCreateHelp()
    {
        $this->addRole(Role::ADMIN);
        $data = [
            'slug'      => 'banana-slug',
            'title'     => 'Yellow Banana Slug',
            'body'      => 'Who will think of the banana slugs?'
        ];

        $response = $this->json('POST', 'help', [
            'help' => $data
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('help', $data);
    }

    /*
     * Update a help document
     */

    public function testUpdateHelp()
    {
        $this->addRole(Role::ADMIN);
        $help = Help::factory()->create();

        $response = $this->json('PATCH', "help/{$help->id}", [
            'help' => [ 'slug' => 'slugger-the-slug' ]
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('help', [ 'id' => $help->id, 'slug' => 'slugger-the-slug' ]);
    }

    /*
     * Delete a slot
     */

    public function testDeleteHelp()
    {
        $this->addRole(Role::ADMIN);
        $help = Help::factory()->create();
        $helpId = $help->id;

        $response = $this->json('DELETE', "help/{$helpId}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('help', [ 'id' => $helpId ]);
    }
}
