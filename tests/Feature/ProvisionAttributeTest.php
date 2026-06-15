<?php

namespace Tests\Feature;

use App\Models\Provision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProvisionAttributeTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        $this->signInUser();
    }

    /**
     * Setting additional_comments prepends a timestamped, callsign-prefixed entry to comments.
     */

    public function testAdditionalCommentsPrependsToComments(): void
    {
        $provision = Provision::factory()->create([
            'person_id' => $this->user->id,
            'source_year' => current_year(),
            'comments' => "existing line\n",
        ]);

        $provision->additional_comments = 'a new note';

        $this->assertStringContainsString('a new note', $provision->comments);
        $this->assertStringContainsString('existing line', $provision->comments);
        $this->assertStringContainsString($this->user->callsign, $provision->comments);
        $this->assertStringStartsNotWith('existing line', $provision->comments);
        $this->assertStringNotContainsString('a new note', (string)($provision->getAttributes()['additional_comments'] ?? ''));
    }

    /**
     * An empty additional_comments value leaves comments untouched.
     */

    public function testAdditionalCommentsEmptyLeavesCommentsUntouched(): void
    {
        $provision = Provision::factory()->create([
            'person_id' => $this->user->id,
            'source_year' => current_year(),
            'comments' => 'untouched',
        ]);

        $provision->additional_comments = '';

        $this->assertEquals('untouched', $provision->comments);
    }
}
