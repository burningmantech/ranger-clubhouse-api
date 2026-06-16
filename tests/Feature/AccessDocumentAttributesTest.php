<?php

namespace Tests\Feature;

use App\Models\AccessDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessDocumentAttributesTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        $this->signInUser();
    }

    /**
     * past_expire_date is true when the expiry year is before the current year.
     */

    public function testPastExpireDateTrueWhenExpired(): void
    {
        $ad = AccessDocument::factory()->create([
            'type' => AccessDocument::SPT,
            'status' => AccessDocument::QUALIFIED,
            'person_id' => $this->user->id,
            'source_year' => current_year() - 1,
            'expiry_date' => (current_year() - 1) . '-12-31',
        ]);

        $this->assertTrue($ad->past_expire_date);
    }

    /**
     * past_expire_date is false when the expiry year is the current year.
     */

    public function testPastExpireDateFalseWhenCurrent(): void
    {
        $ad = AccessDocument::factory()->create([
            'type' => AccessDocument::SPT,
            'status' => AccessDocument::QUALIFIED,
            'person_id' => $this->user->id,
            'source_year' => current_year(),
            'expiry_date' => current_year() . '-12-31',
        ]);

        $this->assertFalse($ad->past_expire_date);
    }

    /**
     * has_staff_credential defaults to false when not set in the underlying attributes.
     */

    public function testHasStaffCredentialDefaultsToFalse(): void
    {
        $ad = AccessDocument::factory()->create([
            'type' => AccessDocument::SPT,
            'status' => AccessDocument::QUALIFIED,
            'person_id' => $this->user->id,
            'source_year' => current_year(),
        ]);

        $this->assertFalse($ad->has_staff_credential);
    }

    /**
     * has_staff_credential reflects the value present in the underlying attributes.
     */

    public function testHasStaffCredentialReflectsUnderlyingAttribute(): void
    {
        $ad = AccessDocument::factory()->create([
            'type' => AccessDocument::SPT,
            'status' => AccessDocument::QUALIFIED,
            'person_id' => $this->user->id,
            'source_year' => current_year(),
        ]);

        $ad->setRawAttributes(array_merge($ad->getAttributes(), ['has_staff_credential' => true]));

        $this->assertTrue($ad->has_staff_credential);
    }

    /**
     * Setting additional_comments prepends a timestamped, callsign-prefixed entry to comments.
     */

    public function testAdditionalCommentsPrependsToComments(): void
    {
        $ad = AccessDocument::factory()->create([
            'type' => AccessDocument::SPT,
            'status' => AccessDocument::QUALIFIED,
            'person_id' => $this->user->id,
            'source_year' => current_year(),
            'comments' => "existing line\n",
        ]);

        $ad->additional_comments = 'a new note';

        $this->assertStringContainsString('a new note', $ad->comments);
        $this->assertStringContainsString('existing line', $ad->comments);
        $this->assertStringContainsString($this->user->callsign, $ad->comments);
        $this->assertStringStartsNotWith('existing line', $ad->comments);
        $this->assertStringNotContainsString('a new note', (string)($ad->getAttributes()['additional_comments'] ?? ''));
    }

    /**
     * An empty additional_comments value leaves comments untouched.
     */

    public function testAdditionalCommentsEmptyLeavesCommentsUntouched(): void
    {
        $ad = AccessDocument::factory()->create([
            'type' => AccessDocument::SPT,
            'status' => AccessDocument::QUALIFIED,
            'person_id' => $this->user->id,
            'source_year' => current_year(),
            'comments' => 'untouched',
        ]);

        $ad->additional_comments = '';

        $this->assertEquals('untouched', $ad->comments);
    }
}
