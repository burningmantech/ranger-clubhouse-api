<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PersonMessage extends ApiModel
{
    /**
     * The database table name.
     * @var string
     */

    protected $table = 'person_message';
    public bool $auditModel = true;

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
        'body',
        'message_from',
        'message_type',
        'recipient_callsign',
        'recipient_team_id',
        'reply_to_id',
        'sender_team_id',
        'sender_type',
        'subject',
    ];

    protected $appends = [
        'has_expired',
        'is_rbs',
        'sender_photo_url',
        'sent_before_today',
        'sent_prior_year',
        'sent_yesterday'
    ];

    public ?string $recipient_callsign;

    // For sorting purposes.
    public bool $recentDelivered = false;
    public int $recentTimestamp = 0;

    const string SENDER_TYPE_PERSON = 'person';
    const string SENDER_TYPE_TEAM = 'team';
    const string SENDER_TYPE_OTHER = 'other';
    const string SENDER_TYPE_RBS = 'rbs';

    // Message was sent through Person Manage or the HQ Interface
    const string MESSAGE_TYPE_NORMAL = 'normal';

    // Message was sent through Me > Messages -- may only send to
    // active or inactive/inactive extension Rangers
    const string MESSAGE_TYPE_CONTACT = 'contact';
    // The same as the above except sent through the Me > Mentors / Mentees page.
    const string MESSAGE_TYPE_MENTOR = 'mentor';

    protected $createRules = [
        'message_from' => 'required|string|max:255',
        'subject' => 'required|string|max:255',
        'body' => 'required|string|max:4000',
    ];


    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'delivered' => 'bool',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * The broadcast if the message was generated through the RBS.
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

    public function creator_person(): belongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function sender_person(): belongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function sender_team(): belongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function recipient_team(): belongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(PersonMessage::class, 'reply_to_id', 'id')->orderBy('created_at');
    }

    public function creator_position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function reply_to(): BelongsTo
    {
        return $this->belongsTo(PersonMessage::class);
    }

    /**
     * Find all messages for a person
     *
     * @param int $personId
     * @return Collection
     */

    public static function findForPerson(int $personId): Collection
    {
        $rows = self::where(function ($w) use ($personId) {
            $w->where('person_id', $personId);
            $w->orWhere('sender_person_id', $personId);
        })->whereNull('reply_to_id')
            ->with([
                'person:id,callsign',
                'creator_person:id,callsign',
                'creator_position:id,title',
                'sender_team:id,title,email',
                'sender_person:id,callsign',
                'replies',
                'replies.creator_person:id,callsign',
                'replies.creator_position:id,title',
                'replies.sender_team:id,title,email',
                'replies.sender_person:id,callsign',
                'replies.person:id,callsign',
            ])->orderBy('person_message.created_at', 'desc')
            ->get();


        foreach ($rows as $row) {
            $recent = $row->replies->last();
            if ($recent) {
                $delivered = true;
                foreach ($row->replies as $reply) {
                    if ($reply->person_id == $personId && !$reply->delivered) {
                        $delivered = false;
                        break;
                    }
                }

                $row->recentDelivered = $delivered;
                $row->recentTimestamp = $recent->created_at->timestamp;
            } else {
                $row->recentDelivered = ($row->sender_person_id == $personId) ? true : $row->delivered;
                $row->recentTimestamp = $row->created_at->timestamp;
            }
        }

        return $rows->sort(function ($a, $b) {
            $delivered = $a->recentDelivered - $b->recentDelivered;
            return !$delivered ? $b->recentTimestamp - $a->recentTimestamp : $delivered;
        })->values();
    }

    private static function saveMarked(PersonMessage $message, bool $delivered): void
    {
        $message->delivered = $delivered;
        $message->saveWithoutValidation();
    }

    /**
     * TODO: Remove this when all the frontend instances have been updated.
     * @return Attribute
     */

    public function creatorCallsign(): Attribute
    {
        return Attribute::make(get: fn() => $this->creator_person?->callsign);
    }

    /**
     * TODO: Remove this when all the frontend instances have been updated.
     * @return Attribute
     */

    public function senderCallsign(): Attribute
    {
        return Attribute::make(get: fn() => $this->sender_person?->callsign ?? $this->message_from);
    }

    /**
     * How many unread messages does the person have?
     *
     * @param int $personId
     * @return int
     */

    public static function countUnread(int $personId): int
    {
        return DB::table('person_message')
            ->where(function ($w) use ($personId) {
                $w->whereNull('sender_person_id');
                $w->orWhere('sender_person_id', '!=', $personId);
            })
            ->where('person_id', $personId)
            ->where('delivered', false)
            ->count();
    }

    public static function mostRecentUnreadMessage(int $personId): ?PersonMessage
    {
        return PersonMessage::where(function ($w) use ($personId) {
            $w->whereNull('sender_person_id');
            $w->orWhere('sender_person_id', '!=', $personId);
        })->where('person_id', $personId)
            ->where('delivered', false)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * validate does triple duty here.
     * - validate the required columns are present
     * - make sure the recipient & sender callsigns are present
     * - setup appropriate fields based on the callsigns
     *
     * @param array $options
     * @return bool  true if the model is valid
     */

    public function save($options = []): bool
    {
        if (!$this->exists) {
            if (empty($this->sender_type)) {
                $this->addError('sender_type', 'Sender type is missing');
                return false;
            }

            $userId = Auth::id();
            // Message created by logged-in user
            $this->creator_person_id = $userId;

            if ($this->message_type !== self::MESSAGE_TYPE_CONTACT && $userId) {
                $this->creator_position_id = DB::table('timesheet')
                    ->where('person_id', $userId)
                    ->whereNull('off_duty')
                    ->value('position_id');
            }

            switch ($this->sender_type) {
                case self::SENDER_TYPE_PERSON:
                    $callsign = $this->message_from;
                    if (empty($callsign)) {
                        $this->addError('message_from', 'Missing from callsign');
                        return false;
                    }
                    $person = Person::findByCallsign($callsign);
                    if (!$person) {
                        $this->addError('message_from', 'Callsign not found ' . $callsign);
                        return false;
                    }

                    $this->sender_person_id = $person->id;
                    $this->message_from = $person->callsign;
                    break;

                case self::SENDER_TYPE_TEAM:
                    if (!$this->validateTeam('sender_team_id')) {
                        return false;
                    }
                    break;

                case self::SENDER_TYPE_OTHER:
                case self::SENDER_TYPE_RBS:
                    break;
                default:
                    $this->addError('sender_type', 'Unknown sender type ' . $this->sender_type);
                    return false;
            }

            if (empty($this->recipient_callsign)) {
                $this->addError('recipient_callsign', 'Missing recipient callsign');
                return false;
            }

            $recipient = Person::findByCallsign($this->recipient_callsign);
            if (!$recipient) {
                $this->addError('recipient_callsign', 'Recipient callsign not found ' . $this->recipient_callsign);
                return false;
            }

            if (in_array($recipient->status, Person::NO_MESSAGES_STATUSES)) {
                $this->addError('recipient_callsign', "Person has a status that does not allow messages.");
                return false;
            }


            switch ($this->message_type) {
                case self::MESSAGE_TYPE_MENTOR:
                case self::MESSAGE_TYPE_CONTACT:
                    if (!in_array($recipient->status, [Person::ACTIVE, Person::INACTIVE, Person::INACTIVE_EXTENSION])) {
                        $this->addError('recipient_callsign', "Person has a status that does not allow messages.");
                        return false;
                    }
                    break;
                case self::MESSAGE_TYPE_NORMAL:
                    // most status allowed.
                    break;
                default:
                    $this->addError('message_type', "Unknown message type [{$this->message_type}]");
                    return false;
            }

            $this->person_id = $recipient->id;

            if ($this->sender_person_id == $this->person_id) {
                $this->delivered = true;
            }

            // TODO: Implement team recipients
            if ($this->recipient_team_id && !$this->validateTeam('recipient_team_id')) {
                $this->addError('recipient_team_id', 'Team message delivery is not implemented yet.');
                return false;
            }

            if ($this->reply_to_id) {
                $reply = $this->reply_to;
                if (!$reply) {
                    $this->addError('reply_to_id', 'Original message not found');
                    return false;
                }

                if ($reply->sender_type != self::SENDER_TYPE_PERSON) {
                    $this->addError('reply_to_id', 'The original message cannot be replied to as it was not from a person.');
                    return false;
                }
            }
            $this->created_at = now();
        }

        return parent::save($options);
    }

    public function validateTeam(string $column): bool
    {
        $teamId = $this->{$column};
        if (!$teamId) {
            $this->addError($column, 'Missing team id');
            return false;
        }
        $team = Team::find($teamId);
        if (!$team) {
            $this->addError($column, 'Team not found');
            return false;
        }

        if (!$team->active) {
            $this->addError($column, 'Team is inactive');
            return false;
        }

        if ($team->type == Team::TYPE_TEAM) {
            $this->addError($column, 'Recipient team is not a Cadre or Delegation');
            return false;
        }

        return true;
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

    /**
     * UI Helper to decide how to display the sent date.
     *
     * @return Attribute
     */
    public function sentBeforeToday(): Attribute
    {
        return Attribute::make(get: fn() => !$this->created_at?->isToday());
    }

    public function sentYesterday(): Attribute
    {
        return Attribute::make(get: fn() => ($this->created_at?->isYesterday()));
    }

    /**
     * UI Helper to decide how to display the sent date.
     *
     * @return Attribute
     */

    public function sentPriorYear(): Attribute
    {
        return Attribute::make(get: fn() => ($this->created_at?->year != current_year()));
    }
}
