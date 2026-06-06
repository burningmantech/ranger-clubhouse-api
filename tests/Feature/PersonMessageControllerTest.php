<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\PersonMessage;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PersonMessageControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    /**
     * Persist a PersonMessage directly, bypassing the create-time validation in
     * PersonMessage::save() so fixtures can set arbitrary state.
     *
     * @param array<string, mixed> $attributes
     * @return PersonMessage
     */
    private function makeMessage(array $attributes = []): PersonMessage
    {
        $message = new PersonMessage();
        $message->forceFill(array_merge([
            'subject' => 'Subject',
            'body' => 'Body',
            'message_from' => 'System',
            'message_type' => PersonMessage::MESSAGE_TYPE_NORMAL,
            'sender_type' => PersonMessage::SENDER_TYPE_PERSON,
            'creator_person_id' => 1,
            'delivered' => false,
            'created_at' => now(),
        ], $attributes));
        $message->saveWithoutValidation();

        return $message;
    }

    /**
     * Build a valid create payload wrapped in the resource key.
     *
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function payload(array $attributes): array
    {
        return ['person_message' => array_merge([
            'subject' => 'Hello',
            'body' => 'A message body',
            'message_type' => PersonMessage::MESSAGE_TYPE_CONTACT,
        ], $attributes)];
    }

    private function firstErrorTitle(TestResponse $response): ?string
    {
        return $response->json('errors.0.title');
    }

    /**
     * A signed-in active user can send a message; the sender is recorded as the user.
     */
    public function testStoreCreatesMessageFromAuthenticatedUser(): void
    {
        $this->signInUser();
        $recipient = Person::factory()->create(['status' => Person::ACTIVE]);

        $response = $this->json('POST', 'messages', $this->payload([
            'recipient_callsign' => $recipient->callsign,
        ]));

        $response->assertStatus(200);
        $this->assertDatabaseHas('person_message', [
            'person_id' => $recipient->id,
            'sender_person_id' => $this->user->id,
            'message_from' => $this->user->callsign,
            'creator_person_id' => $this->user->id,
        ]);
    }

    /**
     * Security: an unprivileged user CANNOT spoof the sender identity. Any supplied
     * sender_type/message_from is ignored and forced to the authenticated user.
     */
    public function testStorePreventsSenderSpoofingByUnprivilegedUser(): void
    {
        $this->signInUser();
        $recipient = Person::factory()->create(['status' => Person::ACTIVE]);
        $victim = Person::factory()->create(['status' => Person::ACTIVE]);

        $response = $this->json('POST', 'messages', $this->payload([
            'recipient_callsign' => $recipient->callsign,
            'sender_type' => PersonMessage::SENDER_TYPE_PERSON,
            'message_from' => $victim->callsign,
        ]));

        $response->assertStatus(200);
        $this->assertEquals($this->user->id, $response->json('person_message.sender_person_id'));
        $this->assertEquals($this->user->callsign, $response->json('person_message.message_from'));
        $this->assertDatabaseMissing('person_message', ['sender_person_id' => $victim->id]);
    }

    /**
     * A privileged user (admin) MAY send a message attributed to another person.
     */
    public function testStoreAllowsPrivilegedUserToSetSender(): void
    {
        $this->signInAsAdmin();
        $recipient = Person::factory()->create(['status' => Person::ACTIVE]);
        $sender = Person::factory()->create(['status' => Person::ACTIVE]);

        $response = $this->json('POST', 'messages', $this->payload([
            'recipient_callsign' => $recipient->callsign,
            'message_type' => PersonMessage::MESSAGE_TYPE_NORMAL,
            'sender_type' => PersonMessage::SENDER_TYPE_PERSON,
            'message_from' => $sender->callsign,
        ]));

        $response->assertStatus(200);
        $this->assertEquals($sender->id, $response->json('person_message.sender_person_id'));
    }

    /**
     * A whitespace-only recipient callsign is rejected (not silently treated as blank).
     */
    public function testStoreRejectsWhitespaceRecipientCallsign(): void
    {
        $this->signInUser();

        $response = $this->json('POST', 'messages', $this->payload([
            'recipient_callsign' => '   ',
        ]));

        $response->assertStatus(422);
        $this->assertEquals('Missing recipient callsign', $this->firstErrorTitle($response));
    }

    /**
     * A callsign that normalizes to more than one person is rejected as ambiguous.
     */
    public function testStoreRejectsAmbiguousRecipientCallsign(): void
    {
        $this->signInUser();
        Person::factory()->create(['callsign' => 'Ambig-One', 'status' => Person::ACTIVE]);
        Person::factory()->create(['callsign' => 'Ambig One', 'status' => Person::ACTIVE]);

        $response = $this->json('POST', 'messages', $this->payload([
            'recipient_callsign' => 'Ambig-One',
        ]));

        $response->assertStatus(422);
        $this->assertEquals('Ambiguous callsign Ambig-One', $this->firstErrorTitle($response));
    }

    /**
     * A message to a recipient whose status forbids messages is rejected.
     */
    public function testStoreRejectsRecipientWithDisallowedStatus(): void
    {
        $this->signInUser();
        $recipient = Person::factory()->create(['status' => Person::SUSPENDED]);

        $response = $this->json('POST', 'messages', $this->payload([
            'recipient_callsign' => $recipient->callsign,
            'message_type' => PersonMessage::MESSAGE_TYPE_NORMAL,
        ]));

        $response->assertStatus(422);
        $this->assertEquals('Person has a status that does not allow messages.', $this->firstErrorTitle($response));
    }

    /**
     * A valid recipient team is rejected because team delivery is unimplemented.
     */
    public function testStoreRejectsRecipientTeam(): void
    {
        $this->signInUser();
        $recipient = Person::factory()->create(['status' => Person::ACTIVE]);
        $team = Team::factory()->create(['active' => true, 'type' => Team::TYPE_CADRE]);

        $response = $this->json('POST', 'messages', $this->payload([
            'recipient_callsign' => $recipient->callsign,
            'recipient_team_id' => $team->id,
        ]));

        $response->assertStatus(422);
        $this->assertEquals('Team message delivery is not implemented yet.', $this->firstErrorTitle($response));
    }

    /**
     * Replying to a non-existent message is rejected.
     */
    public function testStoreRejectsMissingReplyTo(): void
    {
        $this->signInUser();
        $recipient = Person::factory()->create(['status' => Person::ACTIVE]);

        $response = $this->json('POST', 'messages', $this->payload([
            'recipient_callsign' => $recipient->callsign,
            'reply_to_id' => 999999,
        ]));

        $response->assertStatus(422);
        $this->assertEquals('Original message not found', $this->firstErrorTitle($response));
    }

    /**
     * Replying to a message that did not originate from a person is rejected.
     */
    public function testStoreRejectsReplyToNonPersonMessage(): void
    {
        $this->signInUser();
        $recipient = Person::factory()->create(['status' => Person::ACTIVE]);
        $original = $this->makeMessage([
            'person_id' => $this->user->id,
            'sender_type' => PersonMessage::SENDER_TYPE_RBS,
        ]);

        $response = $this->json('POST', 'messages', $this->payload([
            'recipient_callsign' => $recipient->callsign,
            'reply_to_id' => $original->id,
        ]));

        $response->assertStatus(422);
        $this->assertEquals(
            'The original message cannot be replied to as it was not from a person.',
            $this->firstErrorTitle($response)
        );
    }

    /**
     * Regression (M1): an unread root message that has replies must still be reported
     * as undelivered, not silently treated as read.
     */
    public function testFindForPersonReportsUnreadRootWithReplies(): void
    {
        $viewer = Person::factory()->create(['status' => Person::ACTIVE]);
        $other = Person::factory()->create(['status' => Person::ACTIVE]);

        $root = $this->makeMessage([
            'person_id' => $viewer->id,
            'sender_person_id' => $other->id,
            'delivered' => false,
            'created_at' => now()->subDays(2),
        ]);

        // A reply addressed to the original sender (not the viewer), already delivered.
        $this->makeMessage([
            'reply_to_id' => $root->id,
            'person_id' => $other->id,
            'sender_person_id' => $viewer->id,
            'delivered' => true,
            'created_at' => now()->subDay(),
        ]);

        $threads = PersonMessage::findForPerson($viewer->id);

        $this->assertCount(1, $threads);
        $this->assertFalse($threads->first()->recentDelivered);
    }

    /**
     * countUnread counts undelivered inbound messages and excludes the person's own.
     */
    public function testCountUnreadExcludesOwnMessages(): void
    {
        $viewer = Person::factory()->create(['status' => Person::ACTIVE]);
        $other = Person::factory()->create(['status' => Person::ACTIVE]);

        $this->makeMessage([
            'person_id' => $viewer->id,
            'sender_person_id' => $other->id,
            'delivered' => false,
        ]);
        // Copy the viewer sent to themselves should not count.
        $this->makeMessage([
            'person_id' => $viewer->id,
            'sender_person_id' => $viewer->id,
            'delivered' => false,
        ]);

        $this->assertEquals(1, PersonMessage::countUnread($viewer->id));
    }

    /**
     * The recipient can mark their own message as read.
     */
    public function testMarkreadMarksMessageDelivered(): void
    {
        $this->signInUser();
        $message = $this->makeMessage([
            'person_id' => $this->user->id,
            'delivered' => false,
        ]);

        $response = $this->json('PATCH', "messages/{$message->id}/markread", [
            'delivered' => true,
            'person_id' => $this->user->id,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('person_message', ['id' => $message->id, 'delivered' => true]);
    }

    /**
     * Deleting a message removes it together with its replies.
     */
    public function testDestroyDeletesMessageAndReplies(): void
    {
        $this->signInUser();
        $root = $this->makeMessage(['person_id' => $this->user->id]);
        $reply = $this->makeMessage([
            'reply_to_id' => $root->id,
            'person_id' => $this->user->id,
        ]);

        $response = $this->json('DELETE', "messages/{$root->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('person_message', ['id' => $root->id]);
        $this->assertDatabaseMissing('person_message', ['id' => $reply->id]);
    }

    /**
     * A user may list their own messages.
     */
    public function testIndexReturnsOwnMessages(): void
    {
        $this->signInUser();
        $this->makeMessage(['person_id' => $this->user->id]);

        $response = $this->json('GET', 'messages', ['person_id' => $this->user->id]);

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('person_message'));
    }

    /**
     * A user may not list another person's messages.
     */
    public function testIndexForbidsOtherPersonsMessages(): void
    {
        $this->signInUser();
        $other = Person::factory()->create(['status' => Person::ACTIVE]);

        $response = $this->json('GET', 'messages', ['person_id' => $other->id]);

        $response->assertStatus(403);
    }
}
