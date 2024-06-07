<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class Provision extends ApiModel
{
    public $table = 'provision';
    public $timestamps = true;
    public bool $auditModel = true;

    // Statuses
    const string AVAILABLE = 'available';
    const string CLAIMED = 'claimed';
    const string BANKED = 'banked';
    const string USED = 'used';
    const string CANCELLED = 'cancelled';
    const string EXPIRED = 'expired';
    const string SUBMITTED = 'submitted';

    // Meal pass combination
    const string ALL_EAT_PASS = 'all_eat_pass';
    const string EVENT_EAT_PASS = 'event_eat_pass';
    const string PRE_EVENT_EAT_PASS = 'pre_event_eat_pass';
    const string POST_EVENT_EAT_PASS = 'post_event_eat_pass';
    const string PRE_EVENT_EVENT_EAT_PASS = 'pre_event_event_eat_pass';
    const string PRE_POST_EAT_PASS = 'pre_post_eat_pass';
    const string EVENT_POST_EAT_PASS = 'event_post_event_eat_pass';

    const string EVENT_RADIO = 'event_radio';

    const string WET_SPOT = 'wet_spot';

    const array ACTIVE_STATUSES = [
        self::AVAILABLE,
        self::CLAIMED,
        self::BANKED
    ];

    const array CURRENT_STATUSES = [
        self::AVAILABLE,
        self::CLAIMED,
        self::BANKED,
        self::SUBMITTED
    ];

    const array INVALID_STATUSES = [
        self::USED,
        self::CANCELLED,
        self::EXPIRED
    ];

    const array MEAL_TYPES = [
        self::ALL_EAT_PASS,
        self::EVENT_EAT_PASS,
        self::PRE_EVENT_EAT_PASS,
        self::POST_EVENT_EAT_PASS,
        self::PRE_EVENT_EVENT_EAT_PASS,
        self::EVENT_POST_EAT_PASS,
        self::PRE_POST_EAT_PASS,

    ];

    const array MEAL_MATRIX = [
        self::ALL_EAT_PASS => 'pre+event+post',
        self::EVENT_EAT_PASS => 'event',
        self::PRE_EVENT_EAT_PASS => 'pre',
        self::POST_EVENT_EAT_PASS => 'post',
        self::PRE_EVENT_EVENT_EAT_PASS => 'pre+event',
        self::EVENT_POST_EAT_PASS => 'event+post',
        self::PRE_POST_EAT_PASS => 'pre+post'
    ];

    const array TYPE_LABELS = [
        self::ALL_EAT_PASS => 'All Eat Pass',
        self::EVENT_EAT_PASS => 'Event Week Eat Pass',

        self::PRE_EVENT_EAT_PASS => 'Pre-Event Eat Pass',
        self::POST_EVENT_EAT_PASS => 'Post-Event Eat Pass',
        self::PRE_EVENT_EVENT_EAT_PASS => 'Pre+Post Eat Pass',
        self::EVENT_POST_EAT_PASS => 'Event+Post Eat Pass',

        self::EVENT_RADIO => 'Event Radio',
        self::WET_SPOT => 'Wet Spot Access',
    ];

    const array ALL_TYPES = [
        ...self::MEAL_TYPES,
        self::EVENT_RADIO,
        self::WET_SPOT,
    ];

    protected $fillable = [
        'person_id',
        'type',
        'is_allocated',
        'status',
        'source_year',
        'item_count',
        'comments',
        'expires_on',
        'additional_comments',
    ];

    protected function casts(): array
    {
        return [
            'expires_on' => 'datetime:Y-m-d',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'past_expire_date' => 'boolean',
            'is_allocated' => 'boolean',
        ];
    }

    protected $hidden = [
        'additional_comments',   // pseudo-column, write-only. used to append to comments.
    ];

    protected $appends = [
        'past_expire_date',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public static function boot()
    {
        parent::boot();

        self::saving(function ($model) {
            if ($model->type === self::EVENT_RADIO && !$model->item_count) {
                $model->item_count = 1;
            }

            // All allocated provisions in the current year.
            if ($model->is_allocated && !$model->exists) {
                $model->expires_on = current_year();
            }
        });
    }

    /**
     * Save function. Don't allow an allocated provision to be banked.
     *
     * @param $options
     * @return bool
     */

    public function save($options = []): bool
    {
        if ($this->is_allocated && $this->status == self::BANKED) {
            $this->addError('status', 'An allocated provision cannot be banked');
            return false;
        }

        return parent::save($options);
    }

    /**
     * Find records matching a given criteria.
     *
     * Query parameters are:
     *  all: find all current documents otherwise only current documents (not expired, used, invalid)
     *  person_id: find for a specific person
     *  year: for a specific year
     *
     * @param $query
     * @return Collection
     */

    public static function findForQuery($query): Collection
    {

        $status = $query['status'] ?? null;
        $personId = $query['person_id'] ?? null;
        $year = $query['year'] ?? null;
        $type = $query['type'] ?? null;
        $includePerson = $query['include_person'] ?? false;
        $excludeRadio = $query['exclude_radio'] ?? false;

        $sql = self::query()->orderBy('type')->orderBy('source_year');

        if ($status != 'all') {
            if (empty($status)) {
                $sql->whereNotIn('status', self::INVALID_STATUSES);
            } else {
                $sql->whereIn('status', is_array($status) ? $status : explode(',', $status));
            }
        }

        if ($personId) {
            $sql->where('person_id', $personId);
        }

        if ($includePerson) {
            $sql->with('person:id,callsign,status');
        }

        if ($type) {
            $sql->whereIn('type', is_array($type) ? $type : explode(',', $type));
        }

        if ($year) {
            $sql->where('source_year', $year);
        }

        if ($excludeRadio) {
            $sql->where('type', '!=', Provision::EVENT_RADIO);
        }

        $rows = $sql->get();
        if ($includePerson) {
            return $rows->sortBy('person.callsign', SORT_NATURAL | SORT_FLAG_CASE)->values();
        } else {
            return $rows;
        }
    }

    /**
     * Find provisions (available, claimed, banked, submitted) for the given person & type(s)
     *
     * @param int $personId
     * @param array|string $type
     * @param null $isAllocated
     * @return ?Provision
     */

    public static function findAvailableTypeForPerson(int $personId, array|string $type, $isAllocated = null): ?Provision
    {
        if (!is_array($type)) {
            $type = [$type];
        }

        $sql = self::where('person_id', $personId)
            ->whereIn('type', $type)
            ->whereIn('status', [
                self::AVAILABLE,
                self::CLAIMED,
                self::BANKED,
                self::SUBMITTED
            ]);

        if ($isAllocated !== null) {
            $sql->where('is_allocated', $isAllocated);
        }

        return $sql->first();
    }

    /**
     * Does the person have allocated provisions?
     *
     * @param int $personId
     * @return bool
     */
    public static function haveAllocated(int $personId): bool
    {
        return self::where('person_id', $personId)
            ->whereIn('status', [self::AVAILABLE, self::CLAIMED, self::SUBMITTED])
            ->where('is_allocated', true)
            ->exists();
    }

    /**
     * Retrieve all un-submitted earned provisions
     *
     * @param int $personId
     * @return Collection
     */

    public static function retrieveEarned(int $personId): Collection
    {
        return self::where('person_id', $personId)
            ->whereIn('status', [self::AVAILABLE, self::CLAIMED, self::BANKED])
            ->where('is_allocated', false)
            ->get();
    }

    /**
     * Find all item types for a given person, and mark as submitted (consumed).
     *
     * @param int $personId
     * @param array $type
     */

    public static function markSubmittedForBMID(int $personId, array $type)
    {
        $rows = self::whereIn('type', $type)
            ->where('person_id', $personId)
            ->whereIn('status', [self::AVAILABLE, self::CLAIMED])
            ->get();

        foreach ($rows as $row) {
            $row->status = self::SUBMITTED;
            $row->additional_comments = 'Consumed by BMID export';
            $row->auditReason = 'Consumed by BMID export';
            $row->saveWithoutValidation();
        }
    }

    /**
     * Prefix a comment to the comments column.
     *
     * @param string $comment
     * @param Person|string|null $user
     */

    public function addComment(string $comment, Person|string|null $user)
    {
        if ($user instanceof Person) {
            $user = $user->callsign;
        }
        $date = date('n/j/y G:i:s');
        $this->comments = "$date $user: $comment\n{$this->comments}";
    }

    /**
     * Setter for expires_on. Fix the date if it's only a year.
     **/

    public function expiresOn(): Attribute
    {
        return Attribute::make(set: function ($date) {
            if (is_numeric($date)) {
                $date = (string)$date;
            }

            if (strlen($date) == 4) {
                $date .= "-09-15 00:00:00";
            }

            return $date;
        });
    }

    /**
     * Return true if the document expired
     *
     * @return bool
     */

    public function getPastExpireDateAttribute(): bool
    {
        return ($this->expires_on && $this->expires_on->year < current_year());
    }

    /**
     * additional_comments, when set, pre-appends to the comments column with
     * a timestamp and current user's callsign.
     *
     * @param $value
     */

    public function setAdditionalCommentsAttribute($value)
    {
        if (empty($value)) {
            return;
        }

        $date = date('n/j/y G:i:s');
        $callsign = Auth::user()?->callsign ?? "(unknown)";
        $this->comments = "$date $callsign: $value\n" . $this->comments;
    }

    public function getTypeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }
}