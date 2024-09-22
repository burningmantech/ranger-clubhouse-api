<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonMessage extends ApiModel
{
    /**
     * The database table name.
     * @var string
     */
    protected $table = 'person_message';

    /*
     * The following are readonly fields returned for queries:
     * creator_callsign: person.callsign looked up from creator_person_id
     * sender_person_id: person.id looked up from message_from
     *
     * The following are used for message creation:
     * recipient_callsign: used during creation to setup person_message.person_id
     * sender_callsign: used to set message_from during validation
     */

    protected $fillable = [
        'recipient_callsign',
        'message_from',
        'subject',
        'body',
        'reply_to_id'
    ];

    protected $appends = [
        'sender_photo_url',
        'is_rbs',
        'has_expired'
    ];

    public ?string $recipient_callsign;
    public ?string $sender_callsign;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'delivered' => 'bool',
            'expires_at' => 'datetime',
        ];
    }

    protected $createRules = [
        'message_from' => 'required|string|max:255',
        'subject' => 'required|string|max:255',
        'body' => 'required|string|max:4000',
    ];

    /**
     * The sender of the message
     *
     * @return BelongsTo
     */

    public function sender(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * The broadcast if message was generated thru the RBS.
     *
     * @return BelongsTo
     */

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(Broadcast::class);
    }

    /**
     * The person the message belongs to
     *
     * @return BelongsTo
     */

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Find all messages for a person
     *
     * @param int $personId
     * @return Collection
     */

    public static function findForPerson(int $personId): Collection
    {
        return self::where('person_id', $personId)
            ->leftJoin('person as creator', 'creator.id', 'person_message.creator_person_id')
            ->leftJoin('person as sender', 'sender.callsign', 'person_message.message_from')
            ->orderBy('person_message.created_at', 'desc')
            ->get([
                'person_message.*',
                'creator.callsign as creator_callsign',
                'sender.id as sender_person_id',
                'sender.callsign as sender_callsign',
            ]);
    }

    /**
     * How many unread messages does the person have?
     *
     * @param int $personId
     * @return int
     */

    public static function countUnread(int $personId): int
    {
        return PersonMessage::where('person_id', $personId)->where('delivered', false)->count();
    }

    /**
     * validate does triple duty here.
     * - validate the required columns are present
     * - make sure the recipient & sender callsigns are present
     * - setup appropriate fields based on the callsigns
     *
     * @param array $options
     * @return bool  true if model is valid
     */

    public function save($options = []): bool
    {
        if (!$this->exists) {
            if (empty($this->recipient_callsign)) {
                $this->addError('recipient_callsign', 'Recipient callsign is required.');
                return false;
            }
            // Find callsigns and verify contents
            $recipient = Person::findByCallsign($this->recipient_callsign);
            if (!$recipient) {
                $this->addError('recipient_callsign', 'Callsign does not exist');
                return false;
            }

            if (in_array($recipient->status, Person::NO_MESSAGES_STATUSES)) {
                $this->addError('recipient_callsign', "Person is status {$recipient->status} and may not be sent a message.");
                return false;
            }

            $this->person_id = $recipient->id;
        }

        return parent::save();
    }

    /**
     * Mark a message as read, and save the model.
     *
     * @return bool
     */

    public function markRead(): bool
    {
        $this->delivered = true;
        return $this->saveWithoutValidation();
    }

    public function setRecipientCallsignAttribute(?string $value): void
    {
        $this->recipient_callsign = $value;
    }

    /**
     * Retrieve the sender's approved mugshot.
     *
     * @return string
     */

    public function getSenderPhotoUrlAttribute(): string
    {
        $id = $this->sender_person_id ?? null;
        if (!$id) {
            return '';
        }

        return PersonPhoto::retrieveProfileUrlForPerson($id);
    }

    /**
     * Is this message from the RBS?
     *
     * @return bool
     */

    public function getIsRbsAttribute(): bool
    {
        return $this->broadcast_id || stripos($this->message_from ?? '', 'Ranger Broadcasting') !== false;
    }

    /**
     * Has the message expired?
     *
     * @return Attribute
     */

    public function hasExpired(): Attribute
    {
        return Attribute::make(get: function (mixed $value, array $attributes) {
            $expiresAt = $attributes['expires_at'] ?? null;
            if (!$expiresAt) {
                return false;
            }

            return now()->gte(Carbon::parse($expiresAt));
        });
    }
}
