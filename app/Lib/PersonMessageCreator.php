<?php

namespace App\Lib;

use App\Models\Person;
use App\Models\PersonMessage;
use App\Models\Timesheet;
use Illuminate\Support\Facades\Auth;

/**
 * Owns the create-time validation and field derivation for a new PersonMessage.
 * Previously this lived in PersonMessage::save() as a 110 line god method.
 *
 * This class honors the sender identity it is given; it does NOT decide WHO is
 * allowed to send as whom. That authorization is enforced at the request boundary
 * (PersonMessageController), so programmatic senders such as the RBS broadcaster are
 * unaffected. Errors are attached to the model and surfaced via getErrors().
 */
class PersonMessageCreator
{
    /**
     * Validate and populate a new PersonMessage prior to persistence.
     *
     * @param PersonMessage $message
     * @return bool true when the message is valid and ready to save
     */

    public function prepareForCreate(PersonMessage $message): bool
    {
        $user = Auth::user();

        $message->creator_person_id = $user?->id;

        if ($message->message_type !== PersonMessage::MESSAGE_TYPE_CONTACT && $user) {
            $message->creator_position_id = Timesheet::where('person_id', $user->id)
                ->whereNull('off_duty')
                ->value('position_id');
        }

        if (!$this->resolveSender($message)) {
            return false;
        }

        if (!$this->assignRecipient($message)) {
            return false;
        }

        if (!$this->validateReplyTo($message)) {
            return false;
        }

        $message->created_at = now();

        return true;
    }

    /**
     * Resolve the displayed sender from the message's sender fields.
     *
     * @param PersonMessage $message
     * @return bool
     */

    private function resolveSender(PersonMessage $message): bool
    {
        if (empty($message->sender_type)) {
            $message->addError('sender_type', 'Sender type is missing');
            return false;
        }

        switch ($message->sender_type) {
            case PersonMessage::SENDER_TYPE_PERSON:
                return $this->resolvePersonSender($message);

            case PersonMessage::SENDER_TYPE_TEAM:
                return $message->validateTeam('sender_team_id');

            case PersonMessage::SENDER_TYPE_OTHER:
            case PersonMessage::SENDER_TYPE_RBS:
                return true;

            default:
                $message->addError('sender_type', 'Unknown sender type ' . $message->sender_type);
                return false;
        }
    }

    /**
     * Resolve a privileged caller's person sender from the supplied callsign.
     *
     * @param PersonMessage $message
     * @return bool
     */

    private function resolvePersonSender(PersonMessage $message): bool
    {
        $callsign = trim((string) ($message->message_from ?? ''));
        if ($callsign === '') {
            $message->addError('message_from', 'Missing from callsign');
            return false;
        }

        $person = $this->resolveCallsign($callsign, $message, 'message_from', 'Callsign not found ' . $callsign);
        if (!$person) {
            return false;
        }

        $message->sender_person_id = $person->id;
        $message->message_from = $person->callsign;

        return true;
    }

    /**
     * Resolve the recipient from recipient_callsign and enforce status eligibility.
     *
     * @param PersonMessage $message
     * @return bool
     */

    private function assignRecipient(PersonMessage $message): bool
    {
        $callsign = trim((string) ($message->recipient_callsign ?? ''));
        if ($callsign === '') {
            $message->addError('recipient_callsign', 'Missing recipient callsign');
            return false;
        }

        $recipient = $this->resolveCallsign(
            $callsign,
            $message,
            'recipient_callsign',
            'Recipient callsign not found ' . $callsign
        );
        if (!$recipient) {
            return false;
        }

        if (!$this->assertRecipientCanReceive($recipient, $message)) {
            return false;
        }

        $message->person_id = $recipient->id;

        if ($message->sender_person_id == $message->person_id) {
            $message->delivered = true;
        }

        // TODO: Implement team recipients. Any recipient team is rejected for now.
        if ($message->recipient_team_id) {
            if ($message->validateTeam('recipient_team_id')) {
                $message->addError('recipient_team_id', 'Team message delivery is not implemented yet.');
            }
            return false;
        }

        return true;
    }

    /**
     * Enforce recipient status eligibility for the message type.
     *
     * @param Person $recipient
     * @param PersonMessage $message
     * @return bool
     */

    private function assertRecipientCanReceive(Person $recipient, PersonMessage $message): bool
    {
        if (in_array($recipient->status, Person::NO_MESSAGES_STATUSES)) {
            $message->addError('recipient_callsign', 'Person has a status that does not allow messages.');
            return false;
        }

        switch ($message->message_type) {
            case PersonMessage::MESSAGE_TYPE_MENTOR:
            case PersonMessage::MESSAGE_TYPE_CONTACT:
                if (!in_array($recipient->status, [Person::ACTIVE, Person::INACTIVE, Person::INACTIVE_EXTENSION])) {
                    $message->addError('recipient_callsign', 'Person has a status that does not allow messages.');
                    return false;
                }
                return true;

            case PersonMessage::MESSAGE_TYPE_NORMAL:
                return true;

            default:
                $message->addError('message_type', "Unknown message type [{$message->message_type}]");
                return false;
        }
    }

    /**
     * Validate that, when present, the reply target exists and originated from a person.
     *
     * @param PersonMessage $message
     * @return bool
     */

    private function validateReplyTo(PersonMessage $message): bool
    {
        if (!$message->reply_to_id) {
            return true;
        }

        $reply = $message->reply_to;
        if (!$reply) {
            $message->addError('reply_to_id', 'Original message not found');
            return false;
        }

        if ($reply->sender_type != PersonMessage::SENDER_TYPE_PERSON) {
            $message->addError('reply_to_id', 'The original message cannot be replied to as it was not from a person.');
            return false;
        }

        return true;
    }

    /**
     * Resolve a callsign to a single Person, rejecting blank and ambiguous matches.
     *
     * @param string $callsign
     * @param PersonMessage $message
     * @param string $column
     * @param string $notFoundMessage
     * @return Person|null
     */

    private function resolveCallsign(string $callsign, PersonMessage $message, string $column, string $notFoundMessage): ?Person
    {
        $normalized = Person::normalizeCallsign($callsign);
        if ($normalized === '') {
            $message->addError($column, $notFoundMessage);
            return null;
        }

        $matches = Person::where('callsign_normalized', $normalized)->limit(2)->get();

        if ($matches->isEmpty()) {
            $message->addError($column, $notFoundMessage);
            return null;
        }

        if ($matches->count() > 1) {
            $message->addError($column, 'Ambiguous callsign ' . $callsign);
            return null;
        }

        return $matches->first();
    }
}
