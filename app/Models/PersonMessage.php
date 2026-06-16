<?php

namespace App\Models;

use App\Lib\PersonMessageCreator;
use App\Lib\PersonMessageThreadQuery;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    /**
     * The person who created the message via the Clubhouse.
     *
     * NOTE: snake_case relationship method names below are load-bearing -- they are
     * the eager-load keys and JSON serialization keys consumed by the frontend, so
     * they cannot be renamed without a coordinated frontend change.
     *
     * @return BelongsTo
     */

    public function creator_person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * The person the message is attributed to (the displayed sender).
     *
     * @return BelongsTo
     */

    public function sender_person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * The team the message is attributed to.
     *
     * @return BelongsTo
     */

    public function sender_team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * The team the message is addressed to.
     *
     * @return BelongsTo
     */

    public function recipient_team(): BelongsTo
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
     * Find all message threads for a person, enriched and sorted for display.
     *
     * @param int $personId
     * @return Collection
     */

    public static function findForPerson(int $personId): Collection
    {
        return (new PersonMessageThreadQuery())->forPerson($personId);
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
        return self::unreadInboundFor($personId)->count();
    }

    public static function mostRecentUnreadMessage(int $personId): ?PersonMessage
    {
        return self::unreadInboundFor($personId)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Single source of truth for "undelivered message addressed to this person",
     * excluding copies the person sent to themselves.
     *
     * @param int $personId
     * @return Builder
     */

    private static function unreadInboundFor(int $personId): Builder
    {
        return self::query()
            ->where('person_id', $personId)
            ->where('delivered', false)
            ->where(function ($w) use ($personId) {
                $w->whereNull('sender_person_id')
                    ->orWhere('sender_person_id', '!=', $personId);
            });
    }

    /**
     * For new records, delegate create-time authorization, validation, and field
     * derivation to PersonMessageCreator before persisting. Returns false (with errors
     * attached) when preparation fails.
     *
     * @param array $options
     * @return bool true if the model was persisted
     */

    public function save($options = []): bool
    {
        if (!$this->exists && !(new PersonMessageCreator())->prepareForCreate($this)) {
            return false;
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

    /**
     * Bridge mass-assignment of the recipient callsign into the public
     * $recipient_callsign property. This is NOT a database column, so the value
     * must be written to the declared public property rather than the attribute
     * bag (which the public property would otherwise shadow on read). Returning an
     * empty array keeps the value out of the persisted attributes.
     *
     * @return Attribute
     */

    public function recipientCallsign(): Attribute
    {
        return Attribute::make(set: function (?string $value): array {
            $this->recipient_callsign = $value;

            return [];
        });
    }

    /**
     * Retrieve the sender's approved mugshot.
     *
     * @return Attribute
     */

    public function senderPhotoUrl(): Attribute
    {
        return Attribute::make(get: function (mixed $value, array $attributes) {
            $id = $attributes['sender_person_id'] ?? null;
            if (!$id) {
                return '';
            }

            return PersonPhoto::retrieveProfileUrlForPerson($id);
        });
    }

    /**
     * Is this message from the RBS?
     *
     * @return Attribute
     */

    public function isRbs(): Attribute
    {
        return Attribute::make(
            get: fn(mixed $value, array $attributes) => ($attributes['broadcast_id'] ?? null)
                || stripos($attributes['message_from'] ?? '', 'Ranger Broadcasting') !== false
        );
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
