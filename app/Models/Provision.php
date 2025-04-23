<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

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

    const string EVENT_RADIO = 'event_radio';
    const string WET_SPOT = 'wet_spot';
    const string MEALS = 'meals';

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

    const array CAN_USE_STATUSES = [
        self::AVAILABLE,
        self::CLAIMED,
        self::SUBMITTED,
    ];

    const array INVALID_STATUSES = [
        self::USED,
        self::CANCELLED,
        self::EXPIRED
    ];


    const array TYPE_LABELS = [
        self::EVENT_RADIO => 'Event Radio',
        self::WET_SPOT => 'Wet Spot Access',

        // Pseudo meal types - key build up from {pre_event,event_week,post_event}_meals fields
        'pre+event+post' => 'All Eats Pass',
        'event' => 'Event Week Eat Pass',
        'pre' => 'Pre-Event Eat Pass',
        'post' => 'Post-Event Eat Pass',
        'pre+event' => 'Pre-Event & Event Week Eat Pass',
        'event+post' => 'Event Week & Post Event Eat Pass',
        'pre+post' => 'Pre-Event & Post Event Eat Pass',
    ];

    const array ALL_TYPES = [
        self::MEALS,
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
        'pre_event_meals',
        'event_week_meals',
        'post_event_meals',
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
     * @param string $type
     * @param bool|null $isAllocated
     * @return ?Provision
     */

    public static function findAvailableTypeForPerson(int $personId, string $type, ?bool $isAllocated = null): ?Provision
    {

        $sql = self::where('person_id', $personId)
            ->where('type', $type)
            ->whereIn('status', self::CURRENT_STATUSES);

        if ($isAllocated !== null) {
            $sql->where('is_allocated', $isAllocated);
        }

        return $sql->first();
    }

    public static function findAvailableMealsForPerson(int  $personId, bool $isAllocated,
                                                       bool $preMeals, bool $eventMeals, bool $postMeals): ?Provision
    {
        return self::where('person_id', $personId)
            ->where('type', self::MEALS)
            ->whereIn('status', self::CURRENT_STATUSES)
            ->where('is_allocated', $isAllocated)
            ->where('pre_event_meals', $preMeals)
            ->where('event_week_meals', $eventMeals)
            ->where('post_event_meals', $postMeals)
            ->first();
    }

    /**
     * Retrieve all active records for a given type and list of people
     *
     * @param string $type
     * @param array $personIds
     * @return Collection
     */

    public static function retrieveTypeForPersonIds(string $type, array $personIds): Collection
    {
        return self::whereIn('person_id', $personIds)
            ->where('type', $type)
            ->whereIn('status', self::CAN_USE_STATUSES)->get()
            ->groupBy('person_id');
    }

    /**
     * Retrieve the wellness provisions for a given set of people.
     *
     * Banked earned provisions are included if the person has allocated provisions.
     * (You get an allocated cookie, then all the cookies have to be used that year.)
     */

    public static function retrieveUsableForPersonIds(\Illuminate\Support\Collection|array $personIds): Collection
    {
        return self::whereIn('person_id', $personIds)
            ->where(function ($w) {
                $w->whereIn('status', self::CAN_USE_STATUSES)
                    ->orWhere(function ($banked) {
                        $banked->where('status', self::BANKED)
                            ->whereExists(function ($exists) {
                                $exists->selectRaw('1')
                                    ->from('provision as alloc')
                                    ->whereColumn('alloc.person_id', 'provision.person_id')
                                    ->where('alloc.is_allocated', true)
                                    ->whereIn('alloc.status', self::CAN_USE_STATUSES)
                                    ->limit(1);
                            });
                    });
            })->get();
    }

    public static function buildPackage($provisions): array
    {
        $showers = false;
        $haveAllocated = false;
        $radios = 0;

        $preMeals = false;
        $postMeals = false;
        $eventMeals = false;

        foreach ($provisions as $provision) {
            if ($provision->is_allocated) {
                $haveAllocated = true;
            }

            switch ($provision->type) {
                case self::EVENT_RADIO:
                    $count = $provision->item_count ?: 1;
                    if ($count > $radios) {
                        $radios = $count;
                    }
                    break;

                case self::WET_SPOT:
                    $showers = true;
                    break;

                case self::MEALS:
                    if ($provision->pre_event_meals) {
                        $preMeals = true;
                    }

                    if ($provision->post_event_meals) {
                        $postMeals = true;
                    }

                    if ($provision->event_week_meals) {
                        $eventMeals = true;
                    }
                    break;
            }
        }

        return [
            'have_allocated' => $haveAllocated,
            'have_meals' => $preMeals || $eventMeals || $postMeals,
            'meals' => [
                'pre' => $preMeals,
                'event' => $eventMeals,
                'post' => $postMeals,
            ],
            'radios' => $radios,
            'showers' => $showers,
        ];
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
     * @param $provisions
     */

    public static function markSubmittedForBMID($provisions): void
    {
        foreach ($provisions as $row) {
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

    public function addComment(string $comment, Person|string|null $user): void
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

    public function pastExpireDate(): Attribute
    {
        return Attribute::make(get: fn() => ($this->expires_on && $this->expires_on->year < current_year()));
    }

    /**
     * additional_comments, when set, pre-appends to the comments column with
     * a timestamp and current user's callsign.
     *
     * @param $value
     */

    public function setAdditionalCommentsAttribute($value): void
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
        if ($this->type != self::MEALS) {
            return self::TYPE_LABELS[$this->type] ?? $this->type;
        }

        $periods = [];

        if ($this->pre_event_meals) {
            $periods[] = 'pre';
        }

        if ($this->event_week_meals) {
            $periods[] = 'event';
        }

        if ($this->post_event_meals) {
            $periods[] = 'post';
        }

        $mealType = implode('+', $periods);

        return self::TYPE_LABELS[$mealType] ?? $mealType;
    }

    public static function populateMealMatrix(Provision $provision, &$matrix): void
    {
        if ($provision->pre_event_meals) {
            $matrix['pre'] = true;
        }

        if ($provision->event_week_meals) {
            $matrix['event'] = true;
        }

        if ($provision->post_event_meals) {
            $matrix['post'] = true;
        }
    }

    public static function sortMealsMatrix($matrix): string
    {
        if (count($matrix) == 3) {
            return 'all';
        }

        $periods = [];
        if ($matrix['pre'] ?? false) {
            $periods[] = 'pre';
        }
        if ($matrix['event'] ?? false) {
            $periods[] = 'event';
        }
        if ($matrix['post'] ?? false) {
            $periods[] = 'post';
        }

        return implode('+', $periods);
    }

    public function isShowerType(): bool
    {
        return $this->type == self::WET_SPOT;
    }
}